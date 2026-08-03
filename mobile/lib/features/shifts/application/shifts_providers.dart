import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/models/shift.dart';
import '../../../core/network/providers.dart';
import '../data/shifts_repository.dart';

final shiftsRepositoryProvider = Provider<ShiftsRepository>((ref) {
  return ShiftsRepository(ref.watch(dioProvider));
});

final shiftsListProvider = FutureProvider.autoDispose<List<Shift>>((ref) {
  return ref.watch(shiftsRepositoryProvider).list();
});

final shiftOpenFormDataProvider = FutureProvider.autoDispose<ShiftOpenFormData>((ref) {
  return ref.watch(shiftsRepositoryProvider).openFormData();
});

final shiftDetailsProvider = FutureProvider.autoDispose.family<ShiftDetails, int>((ref, shiftId) {
  return ref.watch(shiftsRepositoryProvider).show(shiftId);
});

/// حالة الوردية المفتوحة حالياً — يُستخدم في الرئيسية وشاشة إنشاء العمليات.
final currentOpenShiftProvider = FutureProvider.autoDispose<Shift?>((ref) async {
  final shifts = await ref.watch(shiftsRepositoryProvider).list();
  try {
    return shifts.firstWhere((s) => s.isOpen);
  } catch (_) {
    return null;
  }
});
