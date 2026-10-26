import 'package:flutter/material.dart';

/// Compact app wordmark for signed-in landing pages.
class BrandHeader extends StatelessWidget {
  const BrandHeader({super.key, this.subtitle = 'HOMES & COMMUNITIES'});
  final String subtitle;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      const CombinedBrandBadge(),
      const SizedBox(width: 12),
      Flexible(
        child: FittedBox(
          fit: BoxFit.scaleDown,
          alignment: Alignment.centerLeft,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              const Text(
                'BS Units',
                style: TextStyle(
                  fontFamily: 'CormorantGaramond',
                  fontWeight: FontWeight.w600,
                  fontSize: 33,
                  height: 1,
                  letterSpacing: -.5,
                  color: Color(0xFF183B31),
                ),
              ),
              const SizedBox(height: 5),
              Text(
                subtitle.toUpperCase(),
                style: const TextStyle(
                  fontFamily: 'Manrope',
                  fontSize: 8,
                  fontWeight: FontWeight.w600,
                  height: 1.2,
                  letterSpacing: 1.6,
                  color: Color(0xFF6F8071),
                ),
              ),
            ],
          ),
        ),
      ),
    ],
  );
}

/// Both original marks stay intact inside a gradient circular frame.
class CombinedBrandBadge extends StatelessWidget {
  const CombinedBrandBadge({super.key, this.size = 56});
  final double size;

  @override
  Widget build(BuildContext context) => Semantics(
    label: 'BS Land and Home and EAST Group',
    image: true,
    child: Container(
      width: size,
      height: size,
      padding: EdgeInsets.all(size * .055),
      decoration: const BoxDecoration(
        shape: BoxShape.circle,
        gradient: SweepGradient(
          colors: [
            Color(0xFF23764E),
            Color(0xFF8CC8D9),
            Color(0xFFF1D579),
            Color(0xFFB58B38),
            Color(0xFF23764E),
          ],
          stops: [0, .3, .55, .75, 1],
        ),
        boxShadow: [
          BoxShadow(
            color: Color(0x1A174E3D),
            blurRadius: 8,
            offset: Offset(0, 2),
          ),
        ],
      ),
      child: DecoratedBox(
        decoration: const BoxDecoration(
          shape: BoxShape.circle,
          color: Colors.white,
        ),
        child: Center(
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Image.asset(
                'assets/image/bs_brand.png',
                width: size * .36,
                height: size * .30,
                cacheWidth: (size * 2).ceil(),
                fit: BoxFit.contain,
                excludeFromSemantics: true,
              ),
              SizedBox(width: size * .025),
              Container(
                width: .6,
                height: size * .25,
                color: const Color(0xFFD7DDCE),
              ),
              SizedBox(width: size * .025),
              ClipRect(
                child: Align(
                  widthFactor: .84,
                  heightFactor: .65,
                  child: Image.asset(
                    'assets/image/east_brand.png',
                    width: size * .43,
                    cacheWidth: (size * 2).ceil(),
                    fit: BoxFit.contain,
                    excludeFromSemantics: true,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    ),
  );
}
