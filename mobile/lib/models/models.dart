/// Customer account returned by the versioned API.
class Account {
  Account({
    required this.id,
    required this.accountNumber,
    required this.type,
    required this.currency,
    required this.outstandingMinor,
    this.property,
    this.address,
  });

  final String id;
  final String accountNumber;
  final String type;
  final String currency;
  final int outstandingMinor;
  final String? property;
  final String? address;

  factory Account.fromJson(Map<String, dynamic> json) {
    return Account(
      id: json['id'].toString(),
      accountNumber: json['account_number']?.toString() ?? '',
      type: json['type']?.toString() ?? 'account',
      currency: json['currency']?.toString() ?? 'USD',
      outstandingMinor: (json['outstanding_minor'] as num?)?.toInt() ?? 0,
      property: json['property']?.toString(),
      address: json['address']?.toString(),
    );
  }

  String get outstandingLabel => '$currency ${(outstandingMinor / 100).toStringAsFixed(2)}';
}

class Bill {
  Bill({
    required this.id,
    required this.billNumber,
    required this.serviceType,
    required this.totalMinor,
    required this.balanceMinor,
    required this.status,
    this.issueDate,
    this.dueDate,
  });

  final String id;
  final String billNumber;
  final String serviceType;
  final int totalMinor;
  final int balanceMinor;
  final String status;
  final String? issueDate;
  final String? dueDate;

  factory Bill.fromJson(Map<String, dynamic> json) {
    return Bill(
      id: json['id'].toString(),
      billNumber: json['bill_number']?.toString() ?? '',
      serviceType: json['service_type']?.toString() ?? '',
      totalMinor: (json['total_minor'] as num?)?.toInt() ?? 0,
      balanceMinor: (json['balance_minor'] as num?)?.toInt() ?? 0,
      status: json['status']?.toString() ?? '',
      issueDate: json['issue_date']?.toString(),
      dueDate: json['due_date']?.toString(),
    );
  }

  bool get isPayable => status == 'issued' || status == 'partially_paid';

  String get balanceLabel => 'USD ${(balanceMinor / 100).toStringAsFixed(2)}';
}

class PaymentReceipt {
  PaymentReceipt({required this.paymentNumber, this.receiptNumber, required this.amountMinor, required this.receivedAt});

  final String paymentNumber;
  final String? receiptNumber;
  final int amountMinor;
  final String? receivedAt;

  factory PaymentReceipt.fromJson(Map<String, dynamic> json) {
    return PaymentReceipt(
      paymentNumber: json['payment_number']?.toString() ?? '',
      receiptNumber: json['receipt_number']?.toString(),
      amountMinor: (json['amount_minor'] as num?)?.toInt() ?? 0,
      receivedAt: json['received_at']?.toString(),
    );
  }
}

class ServiceRequest {
  ServiceRequest({required this.reference, required this.category, required this.subject, required this.status});

  final String reference;
  final String category;
  final String subject;
  final String status;

  factory ServiceRequest.fromJson(Map<String, dynamic> json) {
    return ServiceRequest(
      reference: json['reference']?.toString() ?? '',
      category: json['category']?.toString() ?? '',
      subject: json['subject']?.toString() ?? '',
      status: json['status']?.toString() ?? '',
    );
  }
}

class HousingWaitingList {
  HousingWaitingList({
    required this.id,
    required this.waitingListNumber,
    required this.nationalId,
    required this.fullName,
    required this.phoneNumber,
    required this.category,
    required this.categoryLabel,
    required this.preferredSuburbs,
    required this.priorityPoints,
    required this.queuePosition,
    required this.totalInQueue,
    required this.status,
    required this.applicationDate,
    required this.expiryDate,
    required this.daysUntilRenewal,
    required this.renewalFeeMinor,
    this.allocatedStandNumber,
  });

  final String id;
  final String waitingListNumber;
  final String nationalId;
  final String fullName;
  final String phoneNumber;
  final String category;
  final String categoryLabel;
  final List<String> preferredSuburbs;
  final int priorityPoints;
  final int queuePosition;
  final int totalInQueue;
  final String status;
  final String applicationDate;
  final String expiryDate;
  final int daysUntilRenewal;
  final int renewalFeeMinor;
  final String? allocatedStandNumber;

  factory HousingWaitingList.fromJson(Map<String, dynamic> json) {
    return HousingWaitingList(
      id: json['id']?.toString() ?? '',
      waitingListNumber: json['waiting_list_number']?.toString() ?? '',
      nationalId: json['national_id']?.toString() ?? '',
      fullName: json['full_name']?.toString() ?? '',
      phoneNumber: json['phone_number']?.toString() ?? '',
      category: json['category']?.toString() ?? 'high_density',
      categoryLabel: json['category_label']?.toString() ?? 'Residential Stand',
      preferredSuburbs: (json['preferred_suburbs'] as List<dynamic>?)
              ?.map((e) => e.toString())
              .toList() ??
          [],
      priorityPoints: (json['priority_points'] as num?)?.toInt() ?? 0,
      queuePosition: (json['queue_position'] as num?)?.toInt() ?? 1,
      totalInQueue: (json['total_in_queue'] as num?)?.toInt() ?? 1,
      status: json['status']?.toString() ?? 'active',
      applicationDate: json['application_date']?.toString() ?? '',
      expiryDate: json['expiry_date']?.toString() ?? '',
      daysUntilRenewal: (json['days_until_renewal'] as num?)?.toInt() ?? 0,
      renewalFeeMinor: (json['renewal_fee_minor'] as num?)?.toInt() ?? 1500,
      allocatedStandNumber: json['allocated_stand_number']?.toString(),
    );
  }

  bool get isRenewalDue => daysUntilRenewal <= 30;
  String get renewalFeeLabel => 'USD ${(renewalFeeMinor / 100).toStringAsFixed(2)}';
}

class CivicItem {
  CivicItem({
    required this.id,
    required this.type,
    required this.title,
    this.category,
    required this.summary,
    this.content,
    this.authorRole,
    this.publishedDate,
    this.eventDate,
    this.venue,
    this.downloadUrl,
    this.resolutionNumber,
    required this.isFeatured,
  });

  final String id;
  final String type; // news, event, resolution, newsletter
  final String title;
  final String? category;
  final String summary;
  final String? content;
  final String? authorRole;
  final String? publishedDate;
  final String? eventDate;
  final String? venue;
  final String? downloadUrl;
  final String? resolutionNumber;
  final bool isFeatured;

  factory CivicItem.fromJson(Map<String, dynamic> json) {
    return CivicItem(
      id: json['id']?.toString() ?? '',
      type: json['type']?.toString() ?? 'news',
      title: json['title']?.toString() ?? '',
      category: json['category']?.toString(),
      summary: json['summary']?.toString() ?? '',
      content: json['content']?.toString(),
      authorRole: json['author_role']?.toString(),
      publishedDate: json['published_date']?.toString(),
      eventDate: json['event_date']?.toString(),
      venue: json['venue']?.toString(),
      downloadUrl: json['download_url']?.toString(),
      resolutionNumber: json['resolution_number']?.toString(),
      isFeatured: json['is_featured'] == true,
    );
  }
}


class HousingAllocationOffer {
  HousingAllocationOffer({
    required this.id,
    required this.allocationReference,
    required this.standNumber,
    required this.suburb,
    required this.categoryLabel,
    required this.areaSqm,
    required this.purchasePriceUsd,
    required this.depositRequiredUsd,
    required this.depositPaidUsd,
    required this.currencyCode,
    required this.covenants,
    required this.allocatedAt,
    required this.offerExpiresAt,
    required this.daysRemaining,
    required this.status,
    this.acceptedAt,
  });

  final String id;
  final String allocationReference;
  final String standNumber;
  final String suburb;
  final String categoryLabel;
  final double areaSqm;
  final String purchasePriceUsd;
  final String depositRequiredUsd;
  final String depositPaidUsd;
  final String currencyCode;
  final List<String> covenants;
  final String allocatedAt;
  final String offerExpiresAt;
  final int daysRemaining;
  final String status;
  final String? acceptedAt;

  factory HousingAllocationOffer.fromJson(Map<String, dynamic> json) {
    return HousingAllocationOffer(
      id: json['id']?.toString() ?? '',
      allocationReference: json['allocation_reference']?.toString() ?? '',
      standNumber: json['stand_number']?.toString() ?? '',
      suburb: json['suburb']?.toString() ?? '',
      categoryLabel: json['category_label']?.toString() ?? 'Residential Stand',
      areaSqm: (json['area_sqm'] as num?)?.toDouble() ?? 0.0,
      purchasePriceUsd: json['purchase_price_usd']?.toString() ?? '0.00',
      depositRequiredUsd: json['deposit_required_usd']?.toString() ?? '0.00',
      depositPaidUsd: json['deposit_paid_usd']?.toString() ?? '0.00',
      currencyCode: json['currency_code']?.toString() ?? 'USD',
      covenants: (json['covenants'] as List<dynamic>?)?.map((e) => e.toString()).toList() ?? [],
      allocatedAt: json['allocated_at']?.toString() ?? '',
      offerExpiresAt: json['offer_expires_at']?.toString() ?? '',
      daysRemaining: (json['days_remaining'] as num?)?.toInt() ?? 0,
      status: json['status']?.toString() ?? 'offer_issued',
      acceptedAt: json['accepted_at']?.toString(),
    );
  }

  bool get isOfferIssued => status == 'offer_issued';
  bool get isAccepted => status == 'accepted';
  bool get isDepositPaid => status == 'deposit_paid' || status == 'agreement_signed';
}

/// Credit Control Payment Plan models (Urban Councils Act [Chapter 29:15] Section 279)
class PaymentPlanOption {
  PaymentPlanOption({
    required this.months,
    required this.totalArrearsUsd,
    required this.interestWaiverUsd,
    required this.netPayableUsd,
    required this.downPaymentUsd,
    required this.monthlyInstallmentUsd,
    required this.monthlyInstallmentMinor,
    required this.downPaymentMinor,
    required this.resolutionCode,
    required this.resolutionTitle,
  });

  final int months;
  final String totalArrearsUsd;
  final String interestWaiverUsd;
  final String netPayableUsd;
  final String downPaymentUsd;
  final String monthlyInstallmentUsd;
  final int monthlyInstallmentMinor;
  final int downPaymentMinor;
  final String resolutionCode;
  final String resolutionTitle;

  factory PaymentPlanOption.fromJson(Map<String, dynamic> json) {
    return PaymentPlanOption(
      months: (json['months'] as num?)?.toInt() ?? 6,
      totalArrearsUsd: json['total_arrears_usd']?.toString() ?? '0.00',
      interestWaiverUsd: json['interest_waiver_usd']?.toString() ?? '0.00',
      netPayableUsd: json['net_payable_usd']?.toString() ?? '0.00',
      downPaymentUsd: json['down_payment_usd']?.toString() ?? '0.00',
      monthlyInstallmentUsd: json['monthly_installment_usd']?.toString() ?? '0.00',
      monthlyInstallmentMinor: (json['monthly_installment_minor'] as num?)?.toInt() ?? 0,
      downPaymentMinor: (json['down_payment_minor'] as num?)?.toInt() ?? 0,
      resolutionCode: json['resolution_code']?.toString() ?? 'FC/89/2026',
      resolutionTitle: json['resolution_title']?.toString() ?? 'Council 50% Interest Amnesty Resolution',
    );
  }
}

class PaymentPlanInstallmentItem {
  PaymentPlanInstallmentItem({
    required this.id,
    required this.installmentNumber,
    required this.dueDate,
    required this.amountUsd,
    required this.paidUsd,
    required this.status,
    this.paidAt,
  });

  final String id;
  final int installmentNumber;
  final String dueDate;
  final String amountUsd;
  final String paidUsd;
  final String status;
  final String? paidAt;

  factory PaymentPlanInstallmentItem.fromJson(Map<String, dynamic> json) {
    return PaymentPlanInstallmentItem(
      id: json['id']?.toString() ?? '',
      installmentNumber: (json['installment_number'] as num?)?.toInt() ?? 1,
      dueDate: json['due_date']?.toString() ?? '',
      amountUsd: json['amount_usd']?.toString() ?? '0.00',
      paidUsd: json['paid_usd']?.toString() ?? '0.00',
      status: json['status']?.toString() ?? 'pending',
      paidAt: json['paid_at']?.toString(),
    );
  }

  bool get isPaid => status == 'paid';
}

class PaymentPlanDetails {
  PaymentPlanDetails({
    required this.id,
    required this.planNumber,
    required this.accountNumber,
    required this.totalArrearsUsd,
    required this.interestWaivedUsd,
    required this.netPayableUsd,
    required this.monthlyInstallmentUsd,
    required this.installmentCount,
    required this.paidInstallments,
    required this.totalPaidUsd,
    required this.disconnectionImmunity,
    required this.resolutionRef,
    required this.startDate,
    required this.endDate,
    required this.status,
    this.nextInstallment,
    required this.installments,
  });

  final String id;
  final String planNumber;
  final String accountNumber;
  final String totalArrearsUsd;
  final String interestWaivedUsd;
  final String netPayableUsd;
  final String monthlyInstallmentUsd;
  final int installmentCount;
  final int paidInstallments;
  final String totalPaidUsd;
  final bool disconnectionImmunity;
  final String resolutionRef;
  final String startDate;
  final String endDate;
  final String status;
  final PaymentPlanInstallmentItem? nextInstallment;
  final List<PaymentPlanInstallmentItem> installments;

  factory PaymentPlanDetails.fromJson(Map<String, dynamic> json) {
    return PaymentPlanDetails(
      id: json['id']?.toString() ?? '',
      planNumber: json['plan_number']?.toString() ?? '',
      accountNumber: json['account_number']?.toString() ?? '',
      totalArrearsUsd: json['total_arrears_usd']?.toString() ?? '0.00',
      interestWaivedUsd: json['interest_waived_usd']?.toString() ?? '0.00',
      netPayableUsd: json['net_payable_usd']?.toString() ?? '0.00',
      monthlyInstallmentUsd: json['monthly_installment_usd']?.toString() ?? '0.00',
      installmentCount: (json['installment_count'] as num?)?.toInt() ?? 0,
      paidInstallments: (json['paid_installments'] as num?)?.toInt() ?? 0,
      totalPaidUsd: json['total_paid_usd']?.toString() ?? '0.00',
      disconnectionImmunity: json['disconnection_immunity'] == true,
      resolutionRef: json['resolution_ref']?.toString() ?? 'FC/89/2026',
      startDate: json['start_date']?.toString() ?? '',
      endDate: json['end_date']?.toString() ?? '',
      status: json['status']?.toString() ?? 'active',
      nextInstallment: json['next_installment'] != null
          ? PaymentPlanInstallmentItem.fromJson(json['next_installment'] as Map<String, dynamic>)
          : null,
      installments: (json['installments'] as List<dynamic>?)
              ?.map((e) => PaymentPlanInstallmentItem.fromJson(e as Map<String, dynamic>))
              .toList() ??
          [],
    );
  }

  bool get isActive => status == 'active';
  bool get isCompleted => status == 'completed';
}

/// Water Metering & Consumption models
class WaterMeterReadingHistory {
  WaterMeterReadingHistory({
    required this.period,
    required this.reading,
    required this.consumption,
    required this.anomaly,
    this.date,
  });

  final String period;
  final double reading;
  final double consumption;
  final String anomaly;
  final String? date;

  factory WaterMeterReadingHistory.fromJson(Map<String, dynamic> json) {
    return WaterMeterReadingHistory(
      period: json['period']?.toString() ?? '',
      reading: (json['reading'] as num?)?.toDouble() ?? 0.0,
      consumption: (json['consumption'] as num?)?.toDouble() ?? 0.0,
      anomaly: json['anomaly']?.toString() ?? 'normal',
      date: json['date']?.toString(),
    );
  }

  bool get isBurstAlert => anomaly == 'burst_alert';
}

class WaterMeterInfo {
  WaterMeterInfo({
    required this.id,
    required this.meterNumber,
    required this.serialNumber,
    required this.meterSize,
    required this.propertyStand,
    required this.locationAddress,
    required this.lastReading,
    this.lastReadingDate,
    required this.latestConsumptionM3,
    required this.averageMonthlyConsumption,
    required this.tariffCategory,
    required this.estimatedWaterUsd,
    required this.estimatedSewerageUsd,
    required this.estimatedTotalUsd,
    required this.history,
  });

  final String id;
  final String meterNumber;
  final String serialNumber;
  final String meterSize;
  final String propertyStand;
  final String locationAddress;
  final double lastReading;
  final String? lastReadingDate;
  final double latestConsumptionM3;
  final double averageMonthlyConsumption;
  final String tariffCategory;
  final String estimatedWaterUsd;
  final String estimatedSewerageUsd;
  final String estimatedTotalUsd;
  final List<WaterMeterReadingHistory> history;

  factory WaterMeterInfo.fromJson(Map<String, dynamic> json) {
    final est = json['estimated_bill'] as Map<String, dynamic>? ?? {};
    final histRaw = (json['readings_history'] as List<dynamic>? ?? const []);

    return WaterMeterInfo(
      id: json['id']?.toString() ?? '',
      meterNumber: json['meter_number']?.toString() ?? '',
      serialNumber: json['serial_number']?.toString() ?? '',
      meterSize: json['meter_size']?.toString() ?? '15mm',
      propertyStand: json['property_stand']?.toString() ?? '',
      locationAddress: json['location_address']?.toString() ?? '',
      lastReading: (json['last_reading'] as num?)?.toDouble() ?? 0.0,
      lastReadingDate: json['last_reading_date']?.toString(),
      latestConsumptionM3: (json['latest_consumption_m3'] as num?)?.toDouble() ?? 0.0,
      averageMonthlyConsumption: (json['average_monthly_consumption'] as num?)?.toDouble() ?? 0.0,
      tariffCategory: json['tariff_category']?.toString() ?? 'domestic',
      estimatedWaterUsd: est['water_charge_usd']?.toString() ?? '0.00',
      estimatedSewerageUsd: est['sewerage_surcharge_usd']?.toString() ?? '0.00',
      estimatedTotalUsd: est['total_usd']?.toString() ?? '0.00',
      history: histRaw.map((e) => WaterMeterReadingHistory.fromJson(e as Map<String, dynamic>)).toList(),
    );
  }
}
