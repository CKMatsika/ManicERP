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

  test('housing waiting list parses queue and renewal metrics', () {
    final hwl = HousingWaitingList.fromJson({
      'id': 'hwl-1',
      'waiting_list_number': 'HWL-2024-00482',
      'national_id': '63-2049182-W-42',
      'full_name': 'Tendai Moyo',
      'phone_number': '+263774123456',
      'category': 'high_density',
      'category_label': 'High Density Residential',
      'preferred_suburbs': ['Dangamvura', 'Chikanga'],
      'priority_points': 25,
      'queue_position': 4,
      'total_in_queue': 6,
      'status': 'active',
      'application_date': '2024-05-10',
      'expiry_date': '2026-12-23',
      'days_until_renewal': 42,
      'renewal_fee_minor': 1500,
    });

    expect(hwl.waitingListNumber, 'HWL-2024-00482');
    expect(hwl.queuePosition, 4);
    expect(hwl.totalInQueue, 6);
    expect(hwl.priorityPoints, 25);
    expect(hwl.isRenewalDue, isFalse);
    expect(hwl.renewalFeeLabel, 'USD 15.00');
  });

  test('civic item parses news, events and resolutions', () {
    final item = CivicItem.fromJson({
      'id': 'civ-1',
      'type': 'event',
      'title': '2027 Municipal Budget Consultation',
      'category': 'Budget Consultation',
      'summary': 'Residents invited to submit budget priorities',
      'event_date': '2026-11-20',
      'venue': 'Civic Centre Main Auditorium',
      'is_featured': true,
    });

    expect(item.type, 'event');
    expect(item.title, '2027 Municipal Budget Consultation');
    expect(item.eventDate, '2026-11-20');
    expect(item.venue, 'Civic Centre Main Auditorium');
    expect(item.isFeatured, isTrue);
  });

  test('housing allocation offer parses stand details and payment terms', () {
    final offer = HousingAllocationOffer.fromJson({
      'id': 'alc-1',
      'allocation_reference': 'ALC-2026-WMLF5F',
      'stand_number': 'STD-4821-CHK',
      'suburb': 'Chikanga',
      'category_label': 'High Density Residential',
      'area_sqm': 300.0,
      'purchase_price_usd': '2400.00',
      'deposit_required_usd': '400.00',
      'deposit_paid_usd': '0.00',
      'currency_code': 'USD',
      'covenants': ['Foundation within 12 months', 'Window level within 24 months'],
      'allocated_at': '2026-11-11',
      'offer_expires_at': '2026-12-11',
      'days_remaining': 30,
      'status': 'offer_issued',
    });

    expect(offer.allocationReference, 'ALC-2026-WMLF5F');
    expect(offer.standNumber, 'STD-4821-CHK');
    expect(offer.suburb, 'Chikanga');
    expect(offer.areaSqm, 300.0);
    expect(offer.purchasePriceUsd, '2400.00');
    expect(offer.depositRequiredUsd, '400.00');
    expect(offer.isOfferIssued, isTrue);
    expect(offer.isAccepted, isFalse);
    expect(offer.covenants.length, 2);
  });

  test('payment plan option and details parse statutory credit control terms', () {
    final opt = PaymentPlanOption.fromJson({
      'months': 6,
      'total_arrears_usd': '49.17',
      'interest_waiver_usd': '15.00',
      'net_payable_usd': '34.17',
      'down_payment_usd': '10.00',
      'monthly_installment_usd': '4.03',
      'monthly_installment_minor': 403,
      'down_payment_minor': 1000,
      'resolution_code': 'FC/89/2026',
      'resolution_title': 'Council 50% Interest Amnesty Resolution',
    });

    expect(opt.months, 6);
    expect(opt.netPayableUsd, '34.17');
    expect(opt.interestWaiverUsd, '15.00');
    expect(opt.resolutionCode, 'FC/89/2026');

    final plan = PaymentPlanDetails.fromJson({
      'id': 'pp-1',
      'plan_number': 'PP-2026-X99Z1',
      'account_number': 'ACC-0000001',
      'total_arrears_usd': '49.17',
      'interest_waived_usd': '15.00',
      'net_payable_usd': '34.17',
      'monthly_installment_usd': '4.03',
      'installment_count': 6,
      'paid_installments': 1,
      'total_paid_usd': '4.03',
      'disconnection_immunity': true,
      'resolution_ref': 'FC/89/2026',
      'start_date': '2026-11-11',
      'end_date': '2027-05-11',
      'status': 'active',
      'next_installment': {
        'id': 'ins-2',
        'installment_number': 2,
        'due_date': '2026-12-11',
        'amount_usd': '4.03',
        'paid_usd': '0.00',
        'status': 'pending',
      },
      'installments': [
        {
          'id': 'ins-1',
          'installment_number': 1,
          'due_date': '2026-11-11',
          'amount_usd': '4.03',
          'paid_usd': '4.03',
          'status': 'paid',
          'paid_at': '2026-11-11T12:00:00Z',
        },
        {
          'id': 'ins-2',
          'installment_number': 2,
          'due_date': '2026-12-11',
          'amount_usd': '4.03',
          'paid_usd': '0.00',
          'status': 'pending',
        },
      ],
    });

    expect(plan.planNumber, 'PP-2026-X99Z1');
    expect(plan.disconnectionImmunity, isTrue);
    expect(plan.isActive, isTrue);
    expect(plan.paidInstallments, 1);
    expect(plan.installmentCount, 6);
    expect(plan.nextInstallment?.installmentNumber, 2);
    expect(plan.installments.first.isPaid, isTrue);
    expect(plan.installments.last.isPaid, isFalse);
  });

  test('water meter info parses index reading, stepped tariff and leak history', () {
    final meter = WaterMeterInfo.fromJson({
      'id': 'mtr-1',
      'meter_number': 'WTR-MTR-00101',
      'serial_number': 'ELSTER-24-998210',
      'meter_size': '15mm',
      'property_stand': 'AV-1024',
      'location_address': 'Stand 1024, 14 Jason Moyo Avenue',
      'last_reading': 142.200,
      'last_reading_date': '2026-10-25',
      'latest_consumption_m3': 17.70,
      'average_monthly_consumption': 16.50,
      'tariff_category': 'domestic',
      'estimated_bill': {
        'water_charge_usd': '11.05',
        'sewerage_surcharge_usd': '2.76',
        'total_usd': '13.81',
      },
      'readings_history': [
        {
          'period': '2026-10',
          'reading': 142.200,
          'consumption': 17.70,
          'anomaly': 'normal',
          'date': '2026-10-25',
        },
        {
          'period': '2026-09',
          'reading': 124.500,
          'consumption': 55.00,
          'anomaly': 'burst_alert',
          'date': '2026-09-25',
        },
      ],
    });

    expect(meter.meterNumber, 'WTR-MTR-00101');
    expect(meter.lastReading, 142.200);
    expect(meter.latestConsumptionM3, 17.70);
    expect(meter.estimatedTotalUsd, '13.81');
    expect(meter.history.length, 2);
    expect(meter.history.first.isBurstAlert, isFalse);
    expect(meter.history.last.isBurstAlert, isTrue);
  });
}
