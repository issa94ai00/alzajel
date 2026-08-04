import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/theme/theme_mode_controller.dart';
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
    final themeMode = ref.watch(themeModeProvider);
    final isDark = themeMode == ThemeMode.dark ||
        (themeMode == ThemeMode.system && MediaQuery.platformBrightnessOf(context) == Brightness.dark);

    return Scaffold(
      body: SafeArea(
        child: Column(
          children: [
            Container(
              width: double.infinity,
              padding: const EdgeInsets.fromLTRB(20, 18, 20, 30),
              decoration: const BoxDecoration(gradient: AppGradients.hero),
              child: Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'مرحباً بعودتك',
                          style: TextStyle(color: Colors.white.withValues(alpha: 0.75), fontSize: 13),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          user?.name ?? '',
                          style: const TextStyle(color: Colors.white, fontSize: 21, fontWeight: FontWeight.w800),
                        ),
                      ],
                    ),
                  ),
                  IconButton(
                    icon: Icon(
                      isDark ? Icons.light_mode_outlined : Icons.dark_mode_outlined,
                      color: Colors.white,
                    ),
                    tooltip: isDark ? 'الوضع الفاتح' : 'الوضع الداكن',
                    onPressed: () => ref.read(themeModeProvider.notifier).toggle(),
                  ),
                  IconButton(
                    icon: const Icon(Icons.person_outline_rounded, color: Colors.white),
                    onPressed: () => context.push('/profile'),
                  ),
                ],
              ),
            ),
            Expanded(
              child: RefreshIndicator(
                onRefresh: () => ref.refresh(currentOpenShiftProvider.future),
                child: ListView(
                  padding: const EdgeInsets.all(20),
                  children: [
                    AsyncValueView(
                      value: currentShift,
                      onRetry: () => ref.invalidate(currentOpenShiftProvider),
                      data: (shift) {
                        final isOpen = shift != null;
                        return Container(
                          padding: const EdgeInsets.all(22),
                          decoration: BoxDecoration(
                            gradient: AppGradients.primary,
                            borderRadius: BorderRadius.circular(24),
                            border: Border.all(color: AppTheme.gold.withValues(alpha: 0.45), width: 1),
                            boxShadow: [
                              BoxShadow(
                                color: AppTheme.navy.withValues(alpha: 0.28),
                                blurRadius: 20,
                                offset: const Offset(0, 10),
                              ),
                            ],
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  Container(
                                    width: 40,
                                    height: 40,
                                    alignment: Alignment.center,
                                    decoration: BoxDecoration(
                                      color: Colors.white.withValues(alpha: 0.16),
                                      shape: BoxShape.circle,
                                    ),
                                    child: Icon(
                                      isOpen ? Icons.lock_open_rounded : Icons.lock_outline_rounded,
                                      color: Colors.white,
                                      size: 20,
                                    ),
                                  ),
                                  const SizedBox(width: 12),
                                  Text(
                                    isOpen ? 'الوردية مفتوحة' : 'لا توجد وردية مفتوحة',
                                    style: const TextStyle(
                                      color: Colors.white,
                                      fontSize: 16,
                                      fontWeight: FontWeight.w700,
                                    ),
                                  ),
                                ],
                              ),
                              if (isOpen) ...[
                                const SizedBox(height: 10),
                                Text(
                                  'فُتحت: ${formatDate(shift.openedAt)}',
                                  style: TextStyle(color: Colors.white.withValues(alpha: 0.75), fontSize: 13),
                                ),
                              ],
                              const SizedBox(height: 20),
                              Row(
                                children: [
                                  Expanded(
                                    child: OutlinedButton(
                                      style: OutlinedButton.styleFrom(
                                        foregroundColor: Colors.white,
                                        side: BorderSide(color: Colors.white.withValues(alpha: 0.6)),
                                        minimumSize: const Size.fromHeight(46),
                                      ),
                                      onPressed: () => context.push(isOpen ? '/shifts/${shift.id}' : '/shifts/open'),
                                      child: Text(isOpen ? 'تفاصيل الوردية' : 'فتح وردية'),
                                    ),
                                  ),
                                  if (isOpen) ...[
                                    const SizedBox(width: 12),
                                    Expanded(
                                      child: FilledButton(
                                        style: FilledButton.styleFrom(
                                          backgroundColor: Colors.white,
                                          foregroundColor: AppTheme.navy,
                                          minimumSize: const Size.fromHeight(46),
                                        ),
                                        onPressed: () => context.push('/transactions/new'),
                                        child: const Text('عملية جديدة'),
                                      ),
                                    ),
                                  ],
                                ],
                              ),
                            ],
                          ),
                        );
                      },
                    ),
                    const SizedBox(height: 24),
                    Row(
                      children: [
                        Container(
                          width: 4,
                          height: 16,
                          decoration: BoxDecoration(
                            color: AppTheme.gold,
                            borderRadius: BorderRadius.circular(2),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Text('وصول سريع', style: Theme.of(context).textTheme.titleMedium),
                      ],
                    ),
                    const SizedBox(height: 12),
                    GridView.count(
                      crossAxisCount: 2,
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      mainAxisSpacing: 12,
                      crossAxisSpacing: 12,
                      childAspectRatio: 1.3,
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
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final accent = isDark ? AppTheme.gold : AppTheme.navy;

    return Card(
      child: InkWell(
        borderRadius: BorderRadius.circular(22),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                width: 38,
                height: 38,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: accent.withValues(alpha: isDark ? 0.16 : 0.08),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(icon, color: accent, size: 20),
              ),
              const SizedBox(height: 12),
              Text(label, style: Theme.of(context).textTheme.titleSmall),
            ],
          ),
        ),
      ),
    );
  }
}
