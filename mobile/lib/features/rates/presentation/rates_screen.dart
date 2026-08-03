import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/models/currency.dart';
import '../../../core/network/api_exception.dart';
import '../../../features/auth/application/auth_controller.dart';
import '../../../shared/widgets/async_value_view.dart';
import '../../../shared/widgets/empty_state.dart';
import '../application/rates_providers.dart';

class RatesScreen extends ConsumerWidget {
  const RatesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final rates = ref.watch(ratesListProvider);
    final isAdmin = ref.watch(authControllerProvider).user?.isAdmin ?? false;

    return Scaffold(
      appBar: AppBar(
        title: const Text('أسعار الصرف'),
        actions: [
          if (isAdmin)
            IconButton(
              icon: const Icon(Icons.tune_rounded),
              tooltip: 'إدارة العملات',
              onPressed: () => context.push('/currencies/manage'),
            ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () => ref.refresh(ratesListProvider.future),
        child: AsyncValueView(
          value: rates,
          onRetry: () => ref.invalidate(ratesListProvider),
          data: (items) {
            if (items.isEmpty) {
              return const EmptyState(message: 'لا توجد عملات نشطة.', icon: Icons.currency_exchange_rounded);
            }
            return ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: items.length,
              separatorBuilder: (_, _) => const SizedBox(height: 10),
              itemBuilder: (context, index) {
                final currency = items[index];
                return _RateCard(
                  currency: currency,
                  editable: isAdmin,
                  onEdit: () => _showEditDialog(context, ref, currency),
                );
              },
            );
          },
        ),
      ),
    );
  }

  Future<void> _showEditDialog(BuildContext context, WidgetRef ref, Currency currency) async {
    final buyController = TextEditingController(
      text: currency.exchangeRate?.buyRate.toString() ?? '',
    );
    final sellController = TextEditingController(
      text: currency.exchangeRate?.sellRate.toString() ?? '',
    );
    bool submitting = false;

    await showDialog(
      context: context,
      builder: (context) {
        return StatefulBuilder(
          builder: (context, setState) {
            return AlertDialog(
              title: Text('تعديل سعر ${currency.code}'),
              content: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  TextField(
                    controller: buyController,
                    keyboardType: const TextInputType.numberWithOptions(decimal: true),
                    decoration: const InputDecoration(labelText: 'سعر الشراء'),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: sellController,
                    keyboardType: const TextInputType.numberWithOptions(decimal: true),
                    decoration: const InputDecoration(labelText: 'سعر البيع'),
                  ),
                ],
              ),
              actions: [
                TextButton(onPressed: () => Navigator.pop(context), child: const Text('إلغاء')),
                FilledButton(
                  onPressed: submitting
                      ? null
                      : () async {
                          setState(() => submitting = true);
                          try {
                            await ref.read(ratesRepositoryProvider).update(
                                  currency.id,
                                  buyRate: buyController.text.trim(),
                                  sellRate: sellController.text.trim(),
                                );
                            ref.invalidate(ratesListProvider);
                            if (context.mounted) {
                              Navigator.pop(context);
                              ScaffoldMessenger.of(context).showSnackBar(
                                SnackBar(content: Text('تم تحديث سعر ${currency.code}.')),
                              );
                            }
                          } on ApiException catch (e) {
                            if (context.mounted) {
                              ScaffoldMessenger.of(context).showSnackBar(
                                SnackBar(content: Text(e.firstFieldError ?? e.message)),
                              );
                            }
                          } finally {
                            setState(() => submitting = false);
                          }
                        },
                  child: const Text('حفظ'),
                ),
              ],
            );
          },
        );
      },
    );
  }
}

class _RateCard extends StatelessWidget {
  const _RateCard({required this.currency, required this.editable, required this.onEdit});

  final Currency currency;
  final bool editable;
  final VoidCallback onEdit;

  @override
  Widget build(BuildContext context) {
    final rate = currency.exchangeRate;
    final colorScheme = Theme.of(context).colorScheme;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          children: [
            CircleAvatar(
              radius: 22,
              backgroundColor: colorScheme.primary.withValues(alpha: 0.12),
              child: Text(currency.symbol, style: TextStyle(color: colorScheme.primary)),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('${currency.name} (${currency.code})', style: Theme.of(context).textTheme.titleMedium),
                  const SizedBox(height: 6),
                  if (rate != null)
                    Text('شراء: ${rate.buyRate}   •   بيع: ${rate.sellRate}')
                  else
                    Text('لم يُحدد سعر بعد', style: TextStyle(color: colorScheme.error)),
                ],
              ),
            ),
            if (editable)
              IconButton(icon: const Icon(Icons.edit_outlined), onPressed: onEdit),
          ],
        ),
      ),
    );
  }
}
