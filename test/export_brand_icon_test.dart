import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:bs_contract_flutter/core/widgets/brand_header.dart';

void main() {
  testWidgets('Export combined brand launcher icon', (tester) async {
    const output = String.fromEnvironment('BRAND_ICON_OUTPUT');
    if (output.isEmpty) return;
    tester.view.physicalSize = const Size(1024, 1024);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final key = GlobalKey();
    await tester.pumpWidget(
      MaterialApp(
        home: RepaintBoundary(
          key: key,
          child: const ColoredBox(
            color: Color(0xFFF4F6EF),
            child: Center(child: CombinedBrandBadge(size: 820)),
          ),
        ),
      ),
    );
    await tester.runAsync(() async {
      for (final asset in [
        'assets/image/bs_brand.png',
        'assets/image/east_brand.png',
      ]) {
        await precacheImage(AssetImage(asset), key.currentContext!);
      }
    });
    await tester.pumpAndSettle();
    await tester.runAsync(() async {
      final boundary =
          key.currentContext!.findRenderObject()! as RenderRepaintBoundary;
      final picture = await boundary.toImage();
      final data = await picture.toByteData(format: ui.ImageByteFormat.png);
      await File(output).writeAsBytes(data!.buffer.asUint8List());
      picture.dispose();
    });
  });
}
