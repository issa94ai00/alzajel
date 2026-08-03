import 'package:intl/intl.dart';

final _numberFormat = NumberFormat.decimalPattern('en');

String formatAmount(double value) => _numberFormat.format(value);

String formatDate(DateTime? date) {
  if (date == null) return '—';
  return DateFormat('yyyy/MM/dd HH:mm', 'en').format(date.toLocal());
}

String formatDateOnly(DateTime? date) {
  if (date == null) return '—';
  return DateFormat('yyyy/MM/dd', 'en').format(date.toLocal());
}
