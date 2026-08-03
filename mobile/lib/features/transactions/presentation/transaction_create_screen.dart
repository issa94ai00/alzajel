import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/models/currency.dart';
import '../../../core/network/api_exception.dart';
import '../../../shared/widgets/async_value_view.dart';
import '../application/transactions_providers.dart';

class TransactionCreateScreen extends ConsumerStatefulWidget {
  const TransactionCreateScreen({super.key});

  @override
  ConsumerState<TransactionCreateScreen> createState() => _TransactionCreateScreenState();
}

class _TransactionCreateScreenState extends ConsumerState<TransactionCreateScreen> {
  Currency? _selectedCurrency;
  String _type = 'buy';
  final _amountController = TextEditingController();
  final _rateController = TextEditingController();
  final _customerNameController = TextEditingController();
  final _customerPhoneController = TextEditingController();
  final _notesController = TextEditingController();
  bool _submitting = false;

  @override
  void dispose() {
    _amountController.dispose();
    _rateController.dispose();
    _customerNameController.dispose();
    _customerPhoneController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_selectedCurrency == null || _amountController.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('يرجى اختيار العملة وإدخال المبلغ.')),
      );
      return;
    }

    setState(() => _submitting = true);
    try {
      await ref.read(transactionsRepositoryProvider).create(
            currencyId: _selectedCurrency!.id,
            type: _type,
            amount: _amountController.text.trim(),
            rate: _rateController.text.trim(),
            customerName: _customerNameController.text.trim(),
            customerPhone: _customerPhoneController.text.trim(),
            notes: _notesController.text.trim(),
          );

      ref.invalidate(transactionsListProvider);

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('تم تسجيل العملية بنجاح.')));
        context.pop();
      }
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.firstFieldError ?? e.message)));
      }
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final formData = ref.watch(transactionCreateFormDataProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('عملية صرف جديدة')),
      body: AsyncValueView(
        value: formData,
        onRetry: () => ref.invalidate(transactionCreateFormDataProvider),
        data: (data) {
          if (data.shift == null) {
            return const Padding(
              padding: EdgeInsets.all(24),
              child: Center(
                child: Text(
                  'يجب فتح وردية أولاً قبل تسجيل عمليات صرافة.',
                  textAlign: TextAlign.center,
                ),
              ),
            );
          }

          _selectedCurrency ??= data.currencies.isNotEmpty ? data.currencies.first : null;

          return ListView(
            padding: const EdgeInsets.all(20),
            children: [
              SegmentedButton<String>(
                segments: const [
                  ButtonSegment(value: 'buy', label: Text('شراء'), icon: Icon(Icons.call_received_rounded)),
                  ButtonSegment(value: 'sell', label: Text('بيع'), icon: Icon(Icons.call_made_rounded)),
                ],
                selected: {_type},
                onSelectionChanged: (selection) => setState(() => _type = selection.first),
              ),
              const SizedBox(height: 18),
              DropdownButtonFormField<Currency>(
                initialValue: _selectedCurrency,
                decoration: const InputDecoration(labelText: 'العملة'),
                items: data.currencies
                    .map((c) => DropdownMenuItem(value: c, child: Text('${c.name} (${c.code})')))
                    .toList(),
                onChanged: (value) => setState(() => _selectedCurrency = value),
              ),
              if (_selectedCurrency?.exchangeRate != null) ...[
                const SizedBox(height: 8),
                Text(
                  'السعر المرجعي — شراء: ${_selectedCurrency!.exchangeRate!.buyRate} / بيع: ${_selectedCurrency!.exchangeRate!.sellRate}',
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ],
              const SizedBox(height: 16),
              TextField(
                controller: _amountController,
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                decoration: const InputDecoration(labelText: 'المبلغ *'),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _rateController,
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                decoration: const InputDecoration(labelText: 'السعر (اختياري — يُستخدم السعر الحالي إن تُرك فارغاً)'),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _customerNameController,
                decoration: const InputDecoration(labelText: 'اسم العميل (اختياري)'),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _customerPhoneController,
                keyboardType: TextInputType.phone,
                decoration: const InputDecoration(labelText: 'هاتف العميل (اختياري)'),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _notesController,
                maxLines: 3,
                decoration: const InputDecoration(labelText: 'ملاحظات (اختياري)'),
              ),
              const SizedBox(height: 20),
              FilledButton(
                onPressed: _submitting ? null : _submit,
                child: _submitting
                    ? const SizedBox(
                        width: 22,
                        height: 22,
                        child: CircularProgressIndicator(strokeWidth: 2.4, color: Colors.white),
                      )
                    : const Text('تسجيل العملية'),
              ),
            ],
          );
        },
      ),
    );
  }
}
