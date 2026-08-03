import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/network/providers.dart';
import '../../auth/application/auth_controller.dart';

class ProfileScreen extends ConsumerStatefulWidget {
  const ProfileScreen({super.key});

  @override
  ConsumerState<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends ConsumerState<ProfileScreen> {
  late final TextEditingController _nameController;
  late final TextEditingController _emailController;
  bool _saving = false;
  bool _editing = false;

  @override
  void initState() {
    super.initState();
    final user = ref.read(authControllerProvider).user;
    _nameController = TextEditingController(text: user?.name ?? '');
    _emailController = TextEditingController(text: user?.email ?? '');
  }

  @override
  void dispose() {
    _nameController.dispose();
    _emailController.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    try {
      await ref.read(dioProvider).put('/profile', data: {
        'name': _nameController.text.trim(),
        'email': _emailController.text.trim(),
      });
      setState(() => _editing = false);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('تم تحديث الملف الشخصي بنجاح.')));
      }
    } catch (e) {
      final message = e is ApiException ? (e.firstFieldError ?? e.message) : 'حدث خطأ غير متوقع.';
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = ref.watch(authControllerProvider).user;

    return Scaffold(
      appBar: AppBar(title: const Text('الملف الشخصي')),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          Center(
            child: CircleAvatar(
              radius: 42,
              backgroundColor: Theme.of(context).colorScheme.primary.withValues(alpha: 0.12),
              child: Text(
                (user?.name.isNotEmpty ?? false) ? user!.name.substring(0, 1) : '؟',
                style: Theme.of(context).textTheme.headlineMedium,
              ),
            ),
          ),
          const SizedBox(height: 24),
          TextField(
            controller: _nameController,
            enabled: _editing,
            decoration: const InputDecoration(labelText: 'الاسم'),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _emailController,
            enabled: _editing,
            keyboardType: TextInputType.emailAddress,
            decoration: const InputDecoration(labelText: 'البريد الإلكتروني'),
          ),
          const SizedBox(height: 8),
          Align(
            alignment: Alignment.centerRight,
            child: Chip(label: Text(user?.isAdmin == true ? 'مدير' : 'موظف')),
          ),
          const SizedBox(height: 20),
          if (_editing)
            FilledButton(
              onPressed: _saving ? null : _save,
              child: _saving
                  ? const SizedBox(
                      width: 22,
                      height: 22,
                      child: CircularProgressIndicator(strokeWidth: 2.4, color: Colors.white),
                    )
                  : const Text('حفظ التغييرات'),
            )
          else
            OutlinedButton(
              onPressed: () => setState(() => _editing = true),
              child: const Text('تعديل البيانات'),
            ),
          const SizedBox(height: 12),
          OutlinedButton.icon(
            onPressed: () => ref.read(authControllerProvider.notifier).logout(),
            icon: const Icon(Icons.logout_rounded),
            label: const Text('تسجيل الخروج'),
            style: OutlinedButton.styleFrom(foregroundColor: Theme.of(context).colorScheme.error),
          ),
        ],
      ),
    );
  }
}
