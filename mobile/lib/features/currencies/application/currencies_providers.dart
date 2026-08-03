import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/models/currency.dart';
import '../../../core/network/providers.dart';
import '../data/currencies_repository.dart';

final currenciesRepositoryProvider = Provider<CurrenciesRepository>((ref) {
  return CurrenciesRepository(ref.watch(dioProvider));
});

final adminCurrenciesListProvider = FutureProvider.autoDispose<List<Currency>>((ref) {
  return ref.watch(currenciesRepositoryProvider).list();
});
