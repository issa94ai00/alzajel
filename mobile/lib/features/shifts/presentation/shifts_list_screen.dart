import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/models/shift.dart';
import '../../../shared/format.dart';
import '../../../shared/widgets/async_value_view.dart';
import '../../../shared/widgets/empty_state.dart';
import '../application/shifts_providers.dart';

class ShiftsListScreen extends ConsumerWidget {
  const ShiftsListScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final shifts = ref.watch(shiftsListProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('الورديات')),
      floatingActionButton: FloatingActionButton.extended(
        heroTag: 'shifts-open-fab',
        onPressed: () => context.push('/shifts/open'),
        icon: const Icon(Icons.add_rounded),
        label: const Text('فتح وردية'),
      ),
      body: RefreshIndicator(
        onRefresh: () => ref.refresh(shiftsListProvider.future),
        child: AsyncValueView(
          value: shifts,
          onRetry: () => ref.invalidate(shiftsListProvider),
          data: (items) {
            if (items.isEmpty) {
              return const EmptyState(message: 'لا توجد ورديات بعد.', icon: Icons.schedule_outlined);
            }
            return ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: items.length,
              separatorBuilder: (_, _) => const SizedBox(height: 10),
              itemBuilder: (context, index) => _ShiftCard(shift: items[index]),
            );
          },
        ),
      ),
    );
  }
}

class _ShiftCard extends StatelessWidget {
  const _ShiftCard({required this.shift});

  final Shift shift;

  @override
  Widget build(BuildContext context) {
    final colorScheme = Theme.of(context).colorScheme;
    final isOpen = shift.isOpen;

    return Card(
      child: InkWell(
        borderRadius: BorderRadius.circular(20),
        onTap: () => context.push('/shifts/${shift.id}'),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: (isOpen ? colorScheme.primary : colorScheme.outline).withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(
                  isOpen ? Icons.lock_open_rounded : Icons.lock_outline_rounded,
                  color: isOpen ? colorScheme.primary : colorScheme.outline,
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('وردية ${formatDateOnly(DateTime.tryParse(shift.businessDate))}',
                        style: Theme.of(context).textTheme.titleMedium),
                    const SizedBox(height: 4),
                    Text(
                      'فُتحت بواسطة ${shift.openedBy?.name ?? '—'}',
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                  ],
                ),
              ),
              Chip(
                label: Text(isOpen ? 'مفتوحة' : 'مغلقة'),
                backgroundColor: (isOpen ? colorScheme.primary : colorScheme.outline).withValues(alpha: 0.12),
                labelStyle: TextStyle(color: isOpen ? colorScheme.primary : colorScheme.outline),
                side: BorderSide.none,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
