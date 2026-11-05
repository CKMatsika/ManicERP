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
