import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/providers.dart';
import '../data/reports_repository.dart';

final reportsRepositoryProvider = Provider<ReportsRepository>((ref) {
  return ReportsRepository(ref.watch(dioProvider));
});

final balancesReportProvider = FutureProvider.autoDispose<Map<String, dynamic>>((ref) {
  return ref.watch(reportsRepositoryProvider).balances();
});

final dailyReportProvider = FutureProvider.autoDispose<Map<String, dynamic>>((ref) {
  return ref.watch(reportsRepositoryProvider).daily();
});

final profitLossReportProvider = FutureProvider.autoDispose<Map<String, dynamic>>((ref) {
  return ref.watch(reportsRepositoryProvider).profitLoss();
});

final currencyMovementReportProvider =
    FutureProvider.autoDispose.family<Map<String, dynamic>, int?>((ref, currencyId) {
  return ref.watch(reportsRepositoryProvider).currencyMovement(currencyId: currencyId);
});
