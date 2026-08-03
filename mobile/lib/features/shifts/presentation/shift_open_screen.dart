import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../shared/widgets/async_value_view.dart';
import '../application/shifts_providers.dart';

class ShiftOpenScreen extends ConsumerStatefulWidget {
  const ShiftOpenScreen({super.key});

  @override
  ConsumerState<ShiftOpenScreen> createState() => _ShiftOpenScreenState();
}

class _ShiftOpenScreenState extends ConsumerState<ShiftOpenScreen> {
  final Map<int, TextEditingController> _controllers = {};
  bool _submitting = false;

  @override
  void dispose() {
    for (final c in _controllers.values) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _submit() async {
    setState(() => _submitting = true);
    try {
      final balances = <int, String>{};
      for (final entry in _controllers.entries) {
        if (entry.value.text.trim().isNotEmpty) {
          balances[entry.key] = entry.value.text.trim();
        }
      }

      await ref.read(shiftsRepositoryProvider).open(balances);

      ref.invalidate(shiftsListProvider);
      ref.invalidate(currentOpenShiftProvider);

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('تم فتح الوردية بنجاح.')),
        );
        context.pop();
      }
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.firstFieldError ?? e.message)),
        );
      }
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final formData = ref.watch(shiftOpenFormDataProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('فتح وردية جديدة')),
      body: AsyncValueView(
        value: formData,
        onRetry: () => ref.invalidate(shiftOpenFormDataProvider),
        data: (data) {
          if (data.currentShiftOpen && !data.canCloseCurrent) {
            return const Padding(
              padding: EdgeInsets.all(24),
              child: Center(
                child: Text(
                  'توجد وردية مفتوحة حالياً. يجب أن يقوم المدير بإغلاقها قبل فتح وردية جديدة.',
                  textAlign: TextAlign.center,
                ),
              ),
            );
          }

          for (final currency in data.currencies) {
            _controllers.putIfAbsent(currency.id, () => TextEditingController());
          }

          return ListView(
            padding: const EdgeInsets.all(20),
            children: [
              if (data.currentShiftOpen)
                Container(
                  padding: const EdgeInsets.all(14),
                  margin: const EdgeInsets.only(bottom: 16),
                  decoration: BoxDecoration(
                    color: Theme.of(context).colorScheme.errorContainer,
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: Text(
                    'توجد وردية مفتوحة حالياً — سيتم إغلاقها تلقائياً عند فتح وردية جديدة.',
                    style: TextStyle(color: Theme.of(context).colorScheme.onErrorContainer),
                  ),
                ),
              Text('الأرصدة الافتتاحية (اختياري)', style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 6),
              Text(
                'اتركها فارغة لاستخدام رصيد إغلاق آخر وردية تلقائياً.',
                style: Theme.of(context).textTheme.bodySmall,
              ),
              const SizedBox(height: 16),
              for (final currency in data.currencies)
                Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: TextField(
                    controller: _controllers[currency.id],
                    keyboardType: const TextInputType.numberWithOptions(decimal: true),
                    decoration: InputDecoration(
                      labelText: '${currency.name} (${currency.code})',
                      prefixText: '${currency.symbol}  ',
                    ),
                  ),
                ),
              const SizedBox(height: 12),
              FilledButton(
                onPressed: _submitting ? null : _submit,
                child: _submitting
                    ? const SizedBox(
                        width: 22,
                        height: 22,
                        child: CircularProgressIndicator(strokeWidth: 2.4, color: Colors.white),
                      )
                    : Text(data.currentShiftOpen ? 'إغلاق الحالية وفتح وردية جديدة' : 'فتح الوردية'),
              ),
            ],
          );
        },
      ),
    );
  }
}
