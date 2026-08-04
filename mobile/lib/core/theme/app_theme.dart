import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

class AppTheme {
  /// أزرق الشعار الأساسي.
  static const navy = Color(0xFF141B4D);

  /// ظل أزرق أغمق للحالات الفاعلة (hover/pressed).
  static const navyDark = Color(0xFF0D1338);

  /// أزرق أفتح (أعلى تدرّج الشعار).
  static const royalBlue = Color(0xFF2E3094);

  /// ذهبي الشعار.
  static const gold = Color(0xFFF2C94C);

  /// خلفية الوضع الفاتح — أبيض بلمسة زرقاء بدل الرمادي التقليدي.
  static const lightBg = Color(0xFFF5F7FC);

  /// خلفية الوضع الداكن — كحلي عميق بدل الرمادي التقليدي.
  static const darkBg = Color(0xFF0D1130);

  /// سطح البطاقات في الوضع الداكن (أفتح قليلاً من الخلفية).
  static const darkSurface = Color(0xFF171C48);

  /// سطح الحقول/الخلفيات الثانوية في الوضع الداكن.
  static const darkSurfaceHigh = Color(0xFF1F2456);

  /// خلفية الحقول في الوضع الفاتح.
  static const lightFieldFill = Color(0xFFEBEFF8);

  /// نص أساسي في الوضع الفاتح.
  static const lightText = Color(0xFF1A1F3C);

  /// نص أساسي في الوضع الداكن.
  static const darkText = Color(0xFFEDEFFC);

  /// نص ثانوي (مُخفّت) في الوضع الفاتح.
  static const lightTextMuted = Color(0xFF5B6088);

  /// نص ثانوي (مُخفّت) في الوضع الداكن.
  static const darkTextMuted = Color(0xFFA6ABD1);

  static ThemeData light() => _build(Brightness.light);

  static ThemeData dark() => _build(Brightness.dark);

  static ThemeData _build(Brightness brightness) {
    final isLight = brightness == Brightness.light;
    final base = ColorScheme.fromSeed(seedColor: navy, brightness: brightness);
    final colorScheme = base.copyWith(
      secondary: gold,
      onSecondary: navy,
      tertiary: gold,
      onTertiary: navy,
      surface: isLight ? lightBg : darkBg,
      onSurface: isLight ? lightText : darkText,
      onSurfaceVariant: isLight ? lightTextMuted : darkTextMuted,
      surfaceContainerLow: isLight ? Colors.white : darkSurface,
      surfaceContainer: isLight ? Colors.white : darkSurface,
      surfaceContainerHighest: isLight ? lightFieldFill : darkSurfaceHigh,
    );

    final baseTextTheme = GoogleFonts.cairoTextTheme(
      isLight ? ThemeData.light().textTheme : ThemeData.dark().textTheme,
    );
    final textTheme = baseTextTheme.apply(
      bodyColor: isLight ? lightText : darkText,
      displayColor: isLight ? lightText : darkText,
    );

    final selectedAccent = isLight ? navy : gold;

    return ThemeData(
      useMaterial3: true,
      brightness: brightness,
      colorScheme: colorScheme,
      scaffoldBackgroundColor: colorScheme.surface,
      textTheme: textTheme,
      appBarTheme: AppBarTheme(
        backgroundColor: isLight ? Colors.white : darkSurface,
        foregroundColor: isLight ? navy : darkText,
        elevation: 0,
        centerTitle: true,
        titleTextStyle: textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700),
      ),
      cardTheme: CardThemeData(
        elevation: 3,
        shadowColor: navy.withValues(alpha: isLight ? 0.14 : 0.4),
        color: colorScheme.surfaceContainerLow,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(22),
          side: BorderSide(color: gold.withValues(alpha: isLight ? 0.16 : 0.22)),
        ),
        margin: EdgeInsets.zero,
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: colorScheme.surfaceContainerHighest,
        labelStyle: TextStyle(color: isLight ? lightTextMuted : darkTextMuted),
        hintStyle: TextStyle(color: isLight ? lightTextMuted : darkTextMuted),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: BorderSide.none,
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: gold, width: 1.8),
        ),
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      ),
      dividerTheme: DividerThemeData(color: gold.withValues(alpha: 0.25), space: 1),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: navy,
          foregroundColor: Colors.white,
          minimumSize: const Size.fromHeight(52),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
          textStyle: textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w600),
        ),
      ),
      floatingActionButtonTheme: FloatingActionButtonThemeData(
        backgroundColor: gold,
        foregroundColor: navy,
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: isLight ? Colors.white : darkSurface,
        indicatorColor: gold.withValues(alpha: isLight ? 0.22 : 0.28),
        elevation: 0,
        height: 68,
        iconTheme: WidgetStateProperty.resolveWith(
          (states) => IconThemeData(
            color: states.contains(WidgetState.selected)
                ? selectedAccent
                : (isLight ? lightTextMuted : darkTextMuted),
          ),
        ),
        labelTextStyle: WidgetStateProperty.resolveWith(
          (states) => TextStyle(
            fontSize: 12,
            fontWeight: states.contains(WidgetState.selected) ? FontWeight.w700 : FontWeight.w500,
            color: states.contains(WidgetState.selected)
                ? selectedAccent
                : (isLight ? lightTextMuted : darkTextMuted),
          ),
        ),
      ),
      chipTheme: ChipThemeData(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
      ),
      progressIndicatorTheme: ProgressIndicatorThemeData(color: selectedAccent),
    );
  }
}

/// تدرّجات الشعار — تُستخدم في الخلفيات البارزة والأزرار الرئيسية.
class AppGradients {
  static const primary = LinearGradient(
    colors: [AppTheme.royalBlue, AppTheme.navy],
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
  );

  static const hero = LinearGradient(
    colors: [AppTheme.royalBlue, AppTheme.navyDark],
    begin: Alignment.topRight,
    end: Alignment.bottomLeft,
  );
}
