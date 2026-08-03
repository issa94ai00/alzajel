import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../features/auth/application/auth_controller.dart';
import '../../../shared/format.dart';
import '../../../shared/widgets/async_value_view.dart';
import '../../../shared/widgets/empty_state.dart';
import '../application/reports_providers.dart';

class ReportsScreen extends ConsumerWidget {
  const ReportsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final isAdmin = ref.watch(authControllerProvider).user?.isAdmin ?? false;
    final tabs = [
      const Tab(text: 'الأرصدة'),
      const Tab(text: 'الحركة اليومية'),
      const Tab(text: 'حركة العملة'),
      if (isAdmin) const Tab(text: 'الأرباح/الخسائر'),
    ];

    return DefaultTabController(
      length: tabs.length,
      child: Scaffold(
        appBar: AppBar(
          title: const Text('التقارير'),
          bottom: TabBar(tabs: tabs, isScrollable: true),
        ),
        body: TabBarView(
          children: [
            const _BalancesTab(),
            const _DailyTab(),
            const _CurrencyMovementTab(),
            if (isAdmin) const _ProfitLossTab(),
          ],
        ),
      ),
    );
  }
}

class _BalancesTab extends ConsumerWidget {
  const _BalancesTab();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final report = ref.watch(balancesReportProvider);

    return AsyncValueView(
      value: report,
      onRetry: () => ref.invalidate(balancesReportProvider),
      data: (data) {
        final balances = (data['balances'] as List<dynamic>? ?? []);
        if (balances.isEmpty) {
          return const EmptyState(message: 'لا توجد وردية مفتوحة حالياً.', icon: Icons.account_balance_wallet_outlined);
        }
        return ListView.separated(
          padding: const EdgeInsets.all(16),
          itemCount: balances.length,
          separatorBuilder: (_, _) => const SizedBox(height: 10),
          itemBuilder: (context, index) {
            final item = balances[index] as Map<String, dynamic>;
            final currency = item['currency'] as Map<String, dynamic>;
            return Card(
              child: ListTile(
                title: Text('${currency['name']} (${currency['code']})'),
                subtitle: Text('افتتاحي: ${formatAmount(double.parse(item['opening_balance'].toString()))}'),
                trailing: Text(
                  formatAmount(double.parse(item['current_balance'].toString())),
                  style: Theme.of(context).textTheme.titleMedium,
                ),
              ),
            );
          },
        );
      },
    );
  }
}

class _DailyTab extends ConsumerWidget {
  const _DailyTab();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final report = ref.watch(dailyReportProvider);

    return AsyncValueView(
      value: report,
      onRetry: () => ref.invalidate(dailyReportProvider),
      data: (data) => _TransactionsRawList(transactions: data['transactions'] as List<dynamic>? ?? []),
    );
  }
}

class _CurrencyMovementTab extends ConsumerStatefulWidget {
  const _CurrencyMovementTab();

  @override
  ConsumerState<_CurrencyMovementTab> createState() => _CurrencyMovementTabState();
}

class _CurrencyMovementTabState extends ConsumerState<_CurrencyMovementTab> {
  int? _selectedCurrencyId;

  @override
  Widget build(BuildContext context) {
    final report = ref.watch(currencyMovementReportProvider(_selectedCurrencyId));

    return AsyncValueView(
      value: report,
      onRetry: () => ref.invalidate(currencyMovementReportProvider(_selectedCurrencyId)),
      data: (data) {
        final currencies = (data['currencies'] as List<dynamic>? ?? []);
        final selected = data['selected_currency'] as Map<String, dynamic>?;
        final selectedId = selected?['id'];

        return Column(
          children: [
            if (currencies.isNotEmpty)
              Padding(
                padding: const EdgeInsets.all(16),
                child: Wrap(
                  spacing: 8,
                  children: currencies.map((c) {
                    final currency = c as Map<String, dynamic>;
                    final currencyId = currency['id'] as int;
                    final isSelected =
                        _selectedCurrencyId == null ? currencyId == selectedId : currencyId == _selectedCurrencyId;
                    return ChoiceChip(
                      label: Text(currency['code'] as String),
                      selected: isSelected,
                      onSelected: (_) {
                        setState(() => _selectedCurrencyId = currencyId);
                      },
                    );
                  }).toList(),
                ),
              ),
            Expanded(
              child: _TransactionsRawList(transactions: data['transactions'] as List<dynamic>? ?? []),
            ),
          ],
        );
      },
    );
  }
}

class _ProfitLossTab extends ConsumerWidget {
  const _ProfitLossTab();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final report = ref.watch(profitLossReportProvider);

    return AsyncValueView(
      value: report,
      onRetry: () => ref.invalidate(profitLossReportProvider),
      data: (data) {
        final shifts = (data['shifts'] as List<dynamic>? ?? []);
        if (shifts.isEmpty) {
          return const EmptyState(message: 'لا توجد بيانات أرباح/خسائر.', icon: Icons.bar_chart_rounded);
        }
        return ListView.separated(
          padding: const EdgeInsets.all(16),
          itemCount: shifts.length,
          separatorBuilder: (_, _) => const SizedBox(height: 10),
          itemBuilder: (context, index) {
            final item = shifts[index] as Map<String, dynamic>;
            final shift = item['shift'] as Map<String, dynamic>;
            return Card(
              child: ListTile(
                title: Text('وردية ${shift['business_date']}'),
                subtitle: Text('عدد العمليات: ${item['transaction_count']}'),
                trailing: Text(
                  formatAmount(double.parse(item['profit'].toString())),
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        color: Theme.of(context).colorScheme.primary,
                      ),
                ),
              ),
            );
          },
        );
      },
    );
  }
}

class _TransactionsRawList extends StatelessWidget {
  const _TransactionsRawList({required this.transactions});

  final List<dynamic> transactions;

  @override
  Widget build(BuildContext context) {
    if (transactions.isEmpty) {
      return const EmptyState(message: 'لا توجد عمليات في هذه الفترة.', icon: Icons.receipt_long_outlined);
    }

    return ListView.separated(
      padding: const EdgeInsets.all(16),
      itemCount: transactions.length,
      separatorBuilder: (_, _) => const SizedBox(height: 10),
      itemBuilder: (context, index) {
        final t = transactions[index] as Map<String, dynamic>;
        final currency = t['currency'] as Map<String, dynamic>?;
        final isBuy = t['type'] == 'buy';

        return Card(
          child: ListTile(
            title: Text('${isBuy ? 'شراء' : 'بيع'} ${currency?['code'] ?? ''}'),
            subtitle: Text(t['customer_name'] as String? ?? formatDate(DateTime.tryParse(t['created_at'] as String? ?? ''))),
            trailing: Text(formatAmount(double.parse(t['amount'].toString()))),
          ),
        );
      },
    );
  }
}
