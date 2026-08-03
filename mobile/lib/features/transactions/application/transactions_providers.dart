import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/models/exchange_transaction.dart';
import '../../../core/network/providers.dart';
import '../data/transactions_repository.dart';

final transactionsRepositoryProvider = Provider<TransactionsRepository>((ref) {
  return TransactionsRepository(ref.watch(dioProvider));
});

final transactionFiltersProvider = StateProvider.autoDispose<TransactionFilters>((ref) {
  return const TransactionFilters();
});

final transactionsListProvider = FutureProvider.autoDispose<List<ExchangeTransaction>>((ref) {
  final filters = ref.watch(transactionFiltersProvider);
  return ref.watch(transactionsRepositoryProvider).list(filters);
});

final transactionCreateFormDataProvider = FutureProvider.autoDispose<TransactionCreateFormData>((ref) {
  return ref.watch(transactionsRepositoryProvider).createFormData();
});
