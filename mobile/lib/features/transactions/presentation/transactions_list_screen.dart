import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/models/exchange_transaction.dart';
import '../../../shared/format.dart';
import '../../../shared/widgets/async_value_view.dart';
import '../../../shared/widgets/empty_state.dart';
import '../application/transactions_providers.dart';
import '../data/transactions_repository.dart';

class TransactionsListScreen extends ConsumerWidget {
  const TransactionsListScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final transactions = ref.watch(transactionsListProvider);
    final filters = ref.watch(transactionFiltersProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('عمليات الصرف'),
        actions: [
          IconButton(
            icon: const Icon(Icons.filter_alt_outlined),
            onPressed: () => _showFilters(context, ref, filters),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => context.push('/transactions/new'),
        icon: const Icon(Icons.add_rounded),
        label: const Text('عملية جديدة'),
      ),
      body: RefreshIndicator(
        onRefresh: () => ref.refresh(transactionsListProvider.future),
        child: AsyncValueView(
          value: transactions,
          onRetry: () => ref.invalidate(transactionsListProvider),
          data: (items) {
            if (items.isEmpty) {
              return const EmptyState(message: 'لا توجد عمليات صرف مطابقة.', icon: Icons.swap_horiz_rounded);
            }
            return ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: items.length,
              separatorBuilder: (_, _) => const SizedBox(height: 10),
              itemBuilder: (context, index) => _TransactionCard(transaction: items[index]),
            );
          },
        ),
      ),
    );
  }

  Future<void> _showFilters(BuildContext context, WidgetRef ref, TransactionFilters current) async {
    String? search = current.search;
    String? type = current.type;

    await showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      builder: (context) {
        return Padding(
          padding: EdgeInsets.only(
            left: 20,
            right: 20,
            top: 20,
            bottom: MediaQuery.of(context).viewInsets.bottom + 20,
          ),
          child: StatefulBuilder(
            builder: (context, setState) {
              return Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('تصفية العمليات', style: Theme.of(context).textTheme.titleLarge),
                  const SizedBox(height: 16),
                  TextField(
                    decoration: const InputDecoration(labelText: 'بحث (اسم العميل، الهاتف، الملاحظات)'),
                    controller: TextEditingController(text: search),
                    onChanged: (v) => search = v,
                  ),
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 8,
                    children: [
                      ChoiceChip(
                        label: const Text('الكل'),
                        selected: type == null,
                        onSelected: (_) => setState(() => type = null),
                      ),
                      ChoiceChip(
                        label: const Text('شراء'),
                        selected: type == 'buy',
                        onSelected: (_) => setState(() => type = 'buy'),
                      ),
                      ChoiceChip(
                        label: const Text('بيع'),
                        selected: type == 'sell',
                        onSelected: (_) => setState(() => type = 'sell'),
                      ),
                    ],
                  ),
                  const SizedBox(height: 20),
                  FilledButton(
                    onPressed: () {
                      ref.read(transactionFiltersProvider.notifier).state = TransactionFilters(
                        type: type,
                        search: search,
                      );
                      Navigator.pop(context);
                    },
                    child: const Text('تطبيق'),
                  ),
                ],
              );
            },
          ),
        );
      },
    );
  }
}

class _TransactionCard extends StatelessWidget {
  const _TransactionCard({required this.transaction});

  final ExchangeTransaction transaction;

  @override
  Widget build(BuildContext context) {
    final colorScheme = Theme.of(context).colorScheme;
    final isBuy = transaction.isBuy;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          children: [
            Container(
              width: 44,
              height: 44,
              decoration: BoxDecoration(
                color: (isBuy ? colorScheme.primary : colorScheme.tertiary).withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(
                isBuy ? Icons.call_received_rounded : Icons.call_made_rounded,
                color: isBuy ? colorScheme.primary : colorScheme.tertiary,
              ),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    '${isBuy ? 'شراء' : 'بيع'} ${transaction.currency?.code ?? ''}',
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  const SizedBox(height: 4),
                  Text(
                    transaction.customerName ?? formatDate(transaction.createdAt),
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ],
              ),
            ),
            Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Text(formatAmount(transaction.amount), style: Theme.of(context).textTheme.titleMedium),
                Text(
                  'بسعر ${formatAmount(transaction.rate)}',
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
