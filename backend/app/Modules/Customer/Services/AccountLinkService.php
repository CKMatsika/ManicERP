<?php

namespace App\Modules\Customer\Services;

use App\Models\User;
use App\Modules\Customer\Models\AccountLinkVerification;
use App\Modules\Customer\Models\PortalAccountLink;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Revenue\Models\ContactPoint;
use App\Modules\Revenue\Models\CustomerAccount;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Customer account linking. Knowledge of an account number alone never grants
 * access: a link stays pending until a one-time code sent to a verified
 * contact point is confirmed. Revocation is audited.
 */
class AccountLinkService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @return array{link: PortalAccountLink|null, masked: string|null, message: string}
     */
    public function request(User $user, string $accountNumber, string $relationship = 'owner'): array
    {
        $account = CustomerAccount::where('account_number', $accountNumber)->first();

        // Generic response whether or not the account exists.
        $generic = 'If the account number and contact details match our records, a verification code has been sent.';

        if (! $account) {
            return ['link' => null, 'masked' => null, 'message' => $generic];
        }

        $existing = PortalAccountLink::where('user_id', $user->getKey())
            ->where('customer_account_id', $account->id)
            ->first();

        if ($existing && $existing->isActive()) {
            return ['link' => $existing, 'masked' => null, 'message' => 'This account is already linked to your profile.'];
        }

        $email = $this->verifiedEmailFor($account);

        if (! $email) {
            return ['link' => null, 'masked' => null, 'message' => 'This account has no verified email contact. Please visit a council office to link it.'];
        }

        $link = PortalAccountLink::updateOrCreate(
            ['user_id' => $user->getKey(), 'customer_account_id' => $account->id],
            [
                'relationship' => $relationship,
                'status' => 'pending',
                'verification_method' => 'otp',
                'requested_at' => now(),
                'verified_at' => null,
                'revoked_at' => null,
            ],
        );

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        AccountLinkVerification::create([
            'portal_account_link_id' => $link->id,
            'channel' => 'email',
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(15),
            'sent_to_masked' => $this->maskEmail($email),
        ]);

        Mail::raw(
            "Your ManicERP verification code is {$code}. It expires in 15 minutes. Do not share it.",
            fn ($message) => $message->to($email)->subject('ManicERP account verification code'),
        );

        $this->audit->record(
            action: 'customer.account_link.requested',
            subject: $link,
            after: ['account' => $account->account_number, 'channel' => 'email'],
            module: 'customer',
        );

        // The plaintext code is returned only to the calling service so it can
        // be delivered; controllers never serialise it to the client.
        return ['link' => $link, 'masked' => $this->maskEmail($email), 'message' => $generic, 'code' => $code];
    }

    public function verify(User $user, PortalAccountLink $link, string $code): PortalAccountLink
    {
        if ($link->user_id !== $user->getKey()) {
            abort(404);
        }

        if ($link->isActive()) {
            return $link;
        }

        $verification = AccountLinkVerification::where('portal_account_link_id', $link->id)
            ->whereNull('consumed_at')
            ->orderByDesc('created_at')
            ->first();

        if (! $verification) {
            throw new \DomainException('No active verification code. Request a new code.');
        }

        if ($verification->expires_at->isPast()) {
            throw new \DomainException('The verification code has expired. Request a new code.');
        }

        if ($verification->attempts >= 5) {
            throw new \DomainException('Too many incorrect attempts. Request a new code.');
        }

        $verification->attempts++;
        $verification->save();

        if (! Hash::check($code, $verification->code_hash)) {
            throw new \DomainException('The verification code is incorrect.');
        }

        $verification->consumed_at = now();
        $verification->save();

        $link->status = 'verified';
        $link->verified_at = now();
        $link->save();

        $this->audit->record(
            action: 'customer.account_link.verified',
            subject: $link,
            before: ['status' => 'pending'],
            after: ['status' => 'verified', 'account_id' => $link->customer_account_id],
            module: 'customer',
        );

        return $link;
    }

    public function revoke(PortalAccountLink $link, User $user, string $reason): PortalAccountLink
    {
        if ($link->user_id !== $user->getKey()) {
            abort(404);
        }

        $link->status = 'revoked';
        $link->revoked_at = now();
        $link->revoked_reason = $reason;
        $link->save();

        $this->audit->record(
            action: 'customer.account_link.revoked',
            subject: $link,
            before: ['status' => 'verified'],
            after: ['status' => 'revoked', 'reason' => $reason],
            module: 'customer',
        );

        return $link;
    }

    /** @return Collection<int, PortalAccountLink> */
    public function linksFor(User $user)
    {
        return PortalAccountLink::with('account.property')
            ->where('user_id', $user->getKey())
            ->where('status', 'verified')
            ->whereNull('revoked_at')
            ->get();
    }

    public function requireAccount(User $user, string $accountId): CustomerAccount
    {
        $link = PortalAccountLink::where('user_id', $user->getKey())
            ->where('customer_account_id', $accountId)
            ->where('status', 'verified')
            ->whereNull('revoked_at')
            ->first();

        if (! $link) {
            // 404 avoids confirming whether an account exists.
            abort(404);
        }

        return CustomerAccount::findOrFail($accountId);
    }

    private function verifiedEmailFor(CustomerAccount $account): ?string
    {
        $contact = ContactPoint::where('party_id', $account->primary_party_id)
            ->where('type', 'email')
            ->whereNotNull('verified_at')
            ->orderByDesc('is_primary')
            ->first();

        return $contact?->value;
    }

    private function maskEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $visible = substr($name, 0, 2);

        return $visible.str_repeat('*', max(1, strlen($name) - 2)).'@'.$domain;
    }
}
