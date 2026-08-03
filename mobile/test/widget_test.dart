import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:alzajel_mobile/main.dart';

void main() {
  testWidgets('App boots and shows the splash screen', (WidgetTester tester) async {
    await tester.pumpWidget(const ProviderScope(child: AlzajelApp()));
    await tester.pump();

    expect(find.byType(AlzajelApp), findsOneWidget);
  });
}
