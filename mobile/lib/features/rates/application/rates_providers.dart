import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/models/currency.dart';
import '../../../core/network/providers.dart';
import '../data/rates_repository.dart';

final ratesRepositoryProvider = Provider<RatesRepository>((ref) {
  return RatesRepository(ref.watch(dioProvider));
});

final ratesListProvider = FutureProvider.autoDispose<List<Currency>>((ref) {
  return ref.watch(ratesRepositoryProvider).list();
});
