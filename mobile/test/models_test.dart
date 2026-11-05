import 'package:flutter_test/flutter_test.dart';
import 'package:manicerp_citizen/models/models.dart';

void main() {
  test('account parses API payload and formats minor units', () {
    final account = Account.fromJson({
      'id': '01abc',
      'account_number': 'ACC-0000001',
      'type': 'rates',
      'currency': 'USD',
      'outstanding_minor': 170000,
      'property': 'AV-1024',
    });

    expect(account.accountNumber, 'ACC-0000001');
    expect(account.outstandingMinor, 170000);
    expect(account.outstandingLabel, 'USD 1700.00');
  });

  test('bill exposes payable state', () {
    final bill = Bill.fromJson({
      'id': 'b1',
      'bill_number': 'BILL-2026-000001',
      'service_type': 'water',
      'total_minor': 2950,
      'balance_minor': 2950,
      'status': 'issued',
    });

    expect(bill.isPayable, isTrue);
    expect(bill.balanceLabel, 'USD 29.50');
  });

  test('receipt keeps the official receipt number separate from the payment', () {
    final receipt = PaymentReceipt.fromJson({
      'payment_number': 'PMT-2026-000001',
      'receipt_number': 'RCPT-2026-000001',
      'amount_minor': 1000,
    });

    expect(receipt.receiptNumber, 'RCPT-2026-000001');
    expect(receipt.paymentNumber, 'PMT-2026-000001');
  });
}
