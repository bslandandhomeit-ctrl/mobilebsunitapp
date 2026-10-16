import 'package:carousel_slider/carousel_slider.dart';
import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/widgets/app_ui.dart';
import '../models/banner_item.dart';

/// Promotion banners from `api/banners` (only the ones not expired). Tapping a
/// banner opens its link, if it has one.
class BannerCarousel extends StatelessWidget {
  const BannerCarousel({super.key, required this.banners});

  final List<BannerItem> banners;

  Future<void> _open(BannerItem banner) async {
    final uri = Uri.tryParse(banner.linkUrl ?? '');
    if (uri == null || !uri.hasScheme) return;
    await launchUrl(uri, mode: LaunchMode.externalApplication);
  }

  @override
  Widget build(BuildContext context) => CarouselSlider(
    options: CarouselOptions(
      height: 170,
      autoPlay: banners.length > 1,
      viewportFraction: 1,
      enlargeCenterPage: false,
    ),
    items: banners
        .map(
          (banner) => Padding(
            padding: const EdgeInsets.symmetric(horizontal: 2),
            child: GestureDetector(
              onTap: () => _open(banner),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(20),
                child: AppImage(url: banner.imageUrl, height: 170),
              ),
            ),
          ),
        )
        .toList(),
  );
}
