import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../shared/format.dart';
import '../../../shared/widgets/async_value_view.dart';
import '../../auth/application/auth_controller.dart';
import '../../shifts/application/shifts_providers.dart';

class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(authControllerProvider).user;
    final currentShift = ref.watch(currentOpenShiftProvider);
    final colorScheme = Theme.of(context).colorScheme;

    return Scaffold(
      appBar: AppBar(
        title: const Text('الرئيسية'),
        actions: [
          IconButton(
            icon: const Icon(Icons.person_outline_rounded),
            onPressed: () => context.push('/profile'),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () => ref.refresh(currentOpenShiftProvider.future),
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            Text(
              'مرحباً، ${user?.name ?? ''}',
              style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 20),
            AsyncValueView(
              value: currentShift,
              onRetry: () => ref.invalidate(currentOpenShiftProvider),
              data: (shift) {
                final isOpen = shift != null;
                return Card(
                  child: Padding(
                    padding: const EdgeInsets.all(20),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Icon(
                              isOpen ? Icons.lock_open_rounded : Icons.lock_outline_rounded,
                              color: isOpen ? colorScheme.primary : colorScheme.outline,
                            ),
                            const SizedBox(width: 10),
                            Text(
                              isOpen ? 'الوردية مفتوحة' : 'لا توجد وردية مفتوحة',
                              style: Theme.of(context).textTheme.titleMedium,
                            ),
                          ],
                        ),
                        if (isOpen) ...[
                          const SizedBox(height: 8),
                          Text('فُتحت: ${formatDate(shift.openedAt)}'),
                        ],
                        const SizedBox(height: 16),
                        Row(
                          children: [
                            Expanded(
                              child: OutlinedButton(
                                onPressed: () => context.push(isOpen ? '/shifts/${shift.id}' : '/shifts/open'),
                                child: Text(isOpen ? 'تفاصيل الوردية' : 'فتح وردية'),
                              ),
                            ),
                            if (isOpen) ...[
                              const SizedBox(width: 12),
                              Expanded(
                                child: FilledButton(
                                  onPressed: () => context.push('/transactions/new'),
                                  child: const Text('عملية جديدة'),
                                ),
                              ),
                            ],
                          ],
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
            const SizedBox(height: 24),
            Text('وصول سريع', style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 12),
            GridView.count(
              crossAxisCount: 2,
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              mainAxisSpacing: 12,
              crossAxisSpacing: 12,
              childAspectRatio: 1.5,
              children: [
                _QuickAction(
                  icon: Icons.schedule_outlined,
                  label: 'الورديات',
                  onTap: () => context.go('/shifts'),
                ),
                _QuickAction(
                  icon: Icons.swap_horiz_rounded,
                  label: 'عمليات الصرف',
                  onTap: () => context.go('/transactions'),
                ),
                _QuickAction(
                  icon: Icons.currency_exchange_rounded,
                  label: 'أسعار الصرف',
                  onTap: () => context.go('/rates'),
                ),
                _QuickAction(
                  icon: Icons.bar_chart_rounded,
                  label: 'التقارير',
                  onTap: () => context.go('/reports'),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _QuickAction extends StatelessWidget {
  const _QuickAction({required this.icon, required this.label, required this.onTap});

  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final colorScheme = Theme.of(context).colorScheme;
    return Card(
      child: InkWell(
        borderRadius: BorderRadius.circular(20),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(icon, color: colorScheme.primary),
              const SizedBox(height: 10),
              Text(label, style: Theme.of(context).textTheme.titleSmall),
            ],
          ),
        ),
      ),
    );
  }
}
