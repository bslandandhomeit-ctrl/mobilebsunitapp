import 'dart:ui';

import 'package:flutter/material.dart';

const authGreen = Color(0xFF174E3D);
const authInk = Color(0xFF183B31);

/// Shared scenery makes the splash-to-login fade feel like one scene.
class AuthBackdrop extends StatelessWidget {
  const AuthBackdrop({super.key, required this.child, this.splash = false});
  final Widget child;
  final bool splash;

  @override
  Widget build(BuildContext context) => Stack(
    fit: StackFit.expand,
    children: [
      const ColoredBox(color: Color(0xFFEEF3EA)),
      ImageFiltered(
        imageFilter: ImageFilter.blur(sigmaX: 14, sigmaY: 14),
        child: Opacity(
          opacity: .27,
          child: Image.asset('assets/image/splash.jpg', fit: BoxFit.cover),
        ),
      ),
      Positioned(
        top: MediaQuery.paddingOf(context).top + 12,
        left: 0,
        right: 0,
        child: ShaderMask(
          shaderCallback: (bounds) => const LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [Colors.white, Colors.white, Colors.transparent],
            stops: [0, .74, 1],
          ).createShader(bounds),
          blendMode: BlendMode.dstIn,
          child: Image.asset(
            'assets/image/splash.jpg',
            height: (MediaQuery.sizeOf(context).width / 2.15).clamp(160, 380),
            fit: BoxFit.contain,
          ),
        ),
      ),
      const DecoratedBox(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [Colors.transparent, Color(0x22F7F8EF), Color(0xDDE7EFE5)],
            stops: [0, .42, 1],
          ),
        ),
      ),
      child,
    ],
  );
}

class AuthWordmark extends StatelessWidget {
  const AuthWordmark({super.key, this.size = 58});
  final double size;
  @override
  Widget build(BuildContext context) => Text(
    'BS Units',
    textAlign: TextAlign.center,
    style: TextStyle(
      fontFamily: 'CormorantGaramond',
      fontSize: size,
      fontWeight: FontWeight.w600,
      letterSpacing: -.8,
      height: 1.05,
      color: authInk,
    ),
  );
}
