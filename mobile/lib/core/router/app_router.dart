import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../features/auth/application/auth_controller.dart';
import '../../features/auth/presentation/login_screen.dart';
import '../../features/auth/presentation/splash_screen.dart';
import '../../features/dashboard/presentation/app_shell.dart';
import '../../features/dashboard/presentation/home_screen.dart';
import '../../features/profile/presentation/profile_screen.dart';
import '../../features/rates/presentation/rates_screen.dart';
import '../../features/reports/presentation/reports_screen.dart';
import '../../features/shifts/presentation/shift_detail_screen.dart';
import '../../features/shifts/presentation/shift_open_screen.dart';
import '../../features/shifts/presentation/shifts_list_screen.dart';
import '../../features/transactions/presentation/transaction_create_screen.dart';
import '../../features/transactions/presentation/transactions_list_screen.dart';

class _AuthRefreshListenable extends ChangeNotifier {
  _AuthRefreshListenable(Ref ref) {
    ref.listen(authControllerProvider.select((s) => s.status), (previous, next) {
      notifyListeners();
    });
  }
}

final routerProvider = Provider<GoRouter>((ref) {
  final refreshListenable = _AuthRefreshListenable(ref);

  return GoRouter(
    initialLocation: '/splash',
    refreshListenable: refreshListenable,
    redirect: (context, state) {
      final authState = ref.read(authControllerProvider);
      final isLoggingIn = state.matchedLocation == '/login';
      final isSplash = state.matchedLocation == '/splash';

      if (authState.status == AuthStatus.unknown) {
        return isSplash ? null : '/splash';
      }

      if (authState.status == AuthStatus.unauthenticated) {
        return isLoggingIn ? null : '/login';
      }

      if (isLoggingIn || isSplash) return '/';

      return null;
    },
    routes: [
      GoRoute(path: '/splash', builder: (context, state) => const SplashScreen()),
      GoRoute(path: '/login', builder: (context, state) => const LoginScreen()),
      GoRoute(path: '/profile', builder: (context, state) => const ProfileScreen()),
      GoRoute(
        path: '/shifts/open',
        builder: (context, state) => const ShiftOpenScreen(),
      ),
      GoRoute(
        path: '/shifts/:id',
        builder: (context, state) => ShiftDetailScreen(
          shiftId: int.parse(state.pathParameters['id']!),
        ),
      ),
      GoRoute(
        path: '/transactions/new',
        builder: (context, state) => const TransactionCreateScreen(),
      ),
      StatefulShellRoute.indexedStack(
        builder: (context, state, navigationShell) => AppShell(navigationShell: navigationShell),
        branches: [
          StatefulShellBranch(routes: [
            GoRoute(path: '/', builder: (context, state) => const HomeScreen()),
          ]),
          StatefulShellBranch(routes: [
            GoRoute(path: '/shifts', builder: (context, state) => const ShiftsListScreen()),
          ]),
          StatefulShellBranch(routes: [
            GoRoute(path: '/transactions', builder: (context, state) => const TransactionsListScreen()),
          ]),
          StatefulShellBranch(routes: [
            GoRoute(path: '/rates', builder: (context, state) => const RatesScreen()),
          ]),
          StatefulShellBranch(routes: [
            GoRoute(path: '/reports', builder: (context, state) => const ReportsScreen()),
          ]),
        ],
      ),
    ],
  );
});
