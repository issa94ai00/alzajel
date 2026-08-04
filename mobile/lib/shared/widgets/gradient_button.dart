import 'package:flutter/material.dart';

import '../../core/theme/app_theme.dart';

/// زر بتدرّج أزرق الشعار — يُستخدم للإجراء الرئيسي في كل شاشة.
class GradientButton extends StatelessWidget {
  const GradientButton({super.key, required this.onPressed, required this.child, this.height = 52});

  final VoidCallback? onPressed;
  final Widget child;
  final double height;

  @override
  Widget build(BuildContext context) {
    final disabled = onPressed == null;

    return Opacity(
      opacity: disabled ? 0.5 : 1,
      child: Container(
        height: height,
        decoration: BoxDecoration(
          gradient: AppGradients.primary,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: AppTheme.gold.withValues(alpha: 0.5), width: 1),
          boxShadow: disabled
              ? null
              : [
                  BoxShadow(
                    color: AppTheme.navy.withValues(alpha: 0.32),
                    blurRadius: 16,
                    offset: const Offset(0, 8),
                  ),
                ],
        ),
        child: Material(
          type: MaterialType.transparency,
          child: InkWell(
            borderRadius: BorderRadius.circular(14),
            onTap: onPressed,
            child: Center(
              child: DefaultTextStyle(
                style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600, fontSize: 16),
                child: IconTheme(
                  data: const IconThemeData(color: Colors.white),
                  child: child,
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
