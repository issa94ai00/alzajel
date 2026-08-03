import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../features/auth/application/auth_controller.dart';
import '../../../shared/format.dart';
import '../../../shared/widgets/async_value_view.dart';
import '../application/shifts_providers.dart';
import '../data/shifts_repository.dart';

class ShiftDetailScreen extends ConsumerStatefulWidget {
  const ShiftDetailScreen({super.key, required this.shiftId});

  final int shiftId;

  @override
  ConsumerState<ShiftDetailScreen> createState() => _ShiftDetailScreenState();
}

class _ShiftDetailScreenState extends ConsumerState<ShiftDetailScreen> {
  bool _closing = false;

  Future<void> _closeShift() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('إغلاق الوردية'),
        content: const Text('هل أنت متأكد من إغلاق هذه الوردية؟ لا يمكن التراجع عن هذا الإجراء.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('إلغاء')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('إغلاق')),
        ],
      ),
    );

    if (confirmed != true) return;

    setState(() => _closing = true);
    try {
      await ref.read(shiftsRepositoryProvider).close(widget.shiftId);
      ref.invalidate(shiftDetailsProvider(widget.shiftId));
      ref.invalidate(shiftsListProvider);
      ref.invalidate(currentOpenShiftProvider);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('تم إغلاق الوردية بنجاح.')));
      }
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _closing = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final details = ref.watch(shiftDetailsProvider(widget.shiftId));
    final isAdmin = ref.watch(authControllerProvider).user?.isAdmin ?? false;

    return Scaffold(
      appBar: AppBar(title: const Text('تفاصيل الوردية')),
      body: AsyncValueView(
        value: details,
        onRetry: () => ref.invalidate(shiftDetailsProvider(widget.shiftId)),
        data: (data) => _buildBody(context, data, isAdmin),
      ),
    );
  }

  Widget _buildBody(BuildContext context, ShiftDetails data, bool isAdmin) {
    final shift = data.shift;
    final profitLoss = data.profitLoss;
    final colorScheme = Theme.of(context).colorScheme;

    return ListView(
      padding: const EdgeInsets.all(20),
      children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.all(18),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text('وردية ${formatDateOnly(DateTime.tryParse(shift.businessDate))}',
                        style: Theme.of(context).textTheme.titleLarge),
                    Chip(
                      label: Text(shift.isOpen ? 'مفتوحة' : 'مغلقة'),
                      backgroundColor:
                          (shift.isOpen ? colorScheme.primary : colorScheme.outline).withValues(alpha: 0.12),
                      side: BorderSide.none,
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                Text('فُتحت: ${formatDate(shift.openedAt)} — ${shift.openedBy?.name ?? '—'}'),
                if (shift.closedAt != null)
                  Text('أُغلقت: ${formatDate(shift.closedAt)} — ${shift.closedBy?.name ?? '—'}'),
              ],
            ),
          ),
        ),
        const SizedBox(height: 20),
        Text('الأرصدة', style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: 10),
        Card(
          child: Padding(
            padding: const EdgeInsets.symmetric(vertical: 4),
            child: Column(
              children: shift.shiftAccounts.map((account) {
                return ListTile(
                  title: Text('${account.currency?.name} (${account.currency?.code})'),
                  subtitle: Text('افتتاحي: ${formatAmount(account.openingBalance)}'),
                  trailing: Text(
                    formatAmount(account.currentBalance),
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                );
              }).toList(),
            ),
          ),
        ),
        const SizedBox(height: 20),
        Text('الأرباح', style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: 10),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(18),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('إجمالي الربح: ${formatAmount(double.tryParse(profitLoss['total_profit'].toString()) ?? 0)}'),
                Text('عدد العمليات: ${profitLoss['transaction_count']}'),
              ],
            ),
          ),
        ),
        if (shift.isOpen && isAdmin) ...[
          const SizedBox(height: 24),
          FilledButton.tonalIcon(
            onPressed: _closing ? null : _closeShift,
            icon: const Icon(Icons.lock_outline_rounded),
            label: Text(_closing ? 'جارٍ الإغلاق...' : 'إغلاق الوردية'),
          ),
        ],
      ],
    );
  }
}
