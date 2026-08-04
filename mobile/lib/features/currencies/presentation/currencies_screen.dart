import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/models/currency.dart';
import '../../../core/network/api_exception.dart';
import '../../../shared/widgets/async_value_view.dart';
import '../../../shared/widgets/empty_state.dart';
import '../application/currencies_providers.dart';

class CurrenciesScreen extends ConsumerWidget {
  const CurrenciesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final currencies = ref.watch(adminCurrenciesListProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('إدارة العملات')),
      floatingActionButton: FloatingActionButton(
        heroTag: 'currencies-add-fab',
        onPressed: () => _showEditor(context, ref, currency: null),
        child: const Icon(Icons.add_rounded),
      ),
      body: RefreshIndicator(
        onRefresh: () => ref.refresh(adminCurrenciesListProvider.future),
        child: AsyncValueView(
          value: currencies,
          onRetry: () => ref.invalidate(adminCurrenciesListProvider),
          data: (items) {
            if (items.isEmpty) {
              return const EmptyState(message: 'لا توجد عملات بعد.', icon: Icons.currency_exchange_rounded);
            }
            return ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: items.length,
              separatorBuilder: (_, _) => const SizedBox(height: 10),
              itemBuilder: (context, index) {
                final currency = items[index];
                return _CurrencyCard(
                  currency: currency,
                  onEdit: () => _showEditor(context, ref, currency: currency),
                );
              },
            );
          },
        ),
      ),
    );
  }

  Future<void> _showEditor(BuildContext context, WidgetRef ref, {Currency? currency}) async {
    final isNew = currency == null;
    final codeController = TextEditingController(text: currency?.code ?? '');
    final nameController = TextEditingController(text: currency?.name ?? '');
    final symbolController = TextEditingController(text: currency?.symbol ?? '');
    bool isActive = currency?.isActive ?? true;
    bool submitting = false;

    await showDialog(
      context: context,
      builder: (context) {
        return StatefulBuilder(
          builder: (context, setState) {
            return AlertDialog(
              title: Text(isNew ? 'عملة جديدة' : 'تعديل ${currency.code}'),
              content: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  TextField(
                    controller: codeController,
                    maxLength: 3,
                    textCapitalization: TextCapitalization.characters,
                    decoration: const InputDecoration(labelText: 'رمز العملة (مثال: USD)'),
                  ),
                  TextField(
                    controller: nameController,
                    decoration: const InputDecoration(labelText: 'اسم العملة'),
                  ),
                  TextField(
                    controller: symbolController,
                    decoration: const InputDecoration(labelText: 'العلامة (اختياري)'),
                  ),
                  if (currency?.isBase == true)
                    const Padding(
                      padding: EdgeInsets.only(top: 8),
                      child: Text(
                        'هذه هي العملة الأساسية للنظام، ولا يمكن تعطيلها.',
                        style: TextStyle(fontSize: 12),
                      ),
                    )
                  else
                    SwitchListTile(
                      contentPadding: EdgeInsets.zero,
                      title: const Text('مفعّلة'),
                      value: isActive,
                      onChanged: (value) => setState(() => isActive = value),
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
                            final repo = ref.read(currenciesRepositoryProvider);
                            if (isNew) {
                              await repo.create(
                                code: codeController.text.trim(),
                                name: nameController.text.trim(),
                                symbol: symbolController.text.trim().isEmpty ? null : symbolController.text.trim(),
                                isActive: isActive,
                              );
                            } else {
                              await repo.update(
                                currency.id,
                                code: codeController.text.trim(),
                                name: nameController.text.trim(),
                                symbol: symbolController.text.trim().isEmpty ? null : symbolController.text.trim(),
                                isActive: currency.isBase ? true : isActive,
                              );
                            }
                            ref.invalidate(adminCurrenciesListProvider);
                            if (context.mounted) {
                              Navigator.pop(context);
                              ScaffoldMessenger.of(context).showSnackBar(
                                SnackBar(content: Text(isNew ? 'تمت إضافة العملة بنجاح.' : 'تم تحديث العملة بنجاح.')),
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

class _CurrencyCard extends StatelessWidget {
  const _CurrencyCard({required this.currency, required this.onEdit});

  final Currency currency;
  final VoidCallback onEdit;

  @override
  Widget build(BuildContext context) {
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
                  Row(
                    children: [
                      if (currency.isBase)
                        Chip(
                          label: const Text('عملة أساس', style: TextStyle(fontSize: 11)),
                          visualDensity: VisualDensity.compact,
                          materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                        ),
                      if (currency.isBase) const SizedBox(width: 6),
                      Chip(
                        label: Text(
                          currency.isActive ? 'مفعّلة' : 'معطّلة',
                          style: const TextStyle(fontSize: 11),
                        ),
                        backgroundColor: currency.isActive
                            ? colorScheme.primaryContainer
                            : colorScheme.surfaceContainerHighest,
                        visualDensity: VisualDensity.compact,
                        materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                      ),
                    ],
                  ),
                ],
              ),
            ),
            IconButton(icon: const Icon(Icons.edit_outlined), onPressed: onEdit),
          ],
        ),
      ),
    );
  }
}
