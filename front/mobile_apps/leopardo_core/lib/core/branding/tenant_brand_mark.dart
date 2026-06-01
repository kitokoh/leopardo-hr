import 'package:flutter/material.dart';
import 'package:leopardo_core/core/branding/tenant_branding.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';

class TenantBrandMark extends StatelessWidget {
  const TenantBrandMark({
    super.key,
    required this.branding,
    this.compact = false,
  });

  final TenantBranding? branding;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final brand = branding;
    if (brand == null) {
      return const SizedBox.shrink();
    }

    final primary = brand.safePrimaryColor;
    final logo = brand.logoUrl;

    return Container(
      padding: EdgeInsets.symmetric(
        horizontal: compact ? 10 : 12,
        vertical: compact ? 7 : 9,
      ),
      decoration: BoxDecoration(
        color: primary.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: primary.withValues(alpha: 0.34)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: compact ? 18 : 22,
            height: compact ? 18 : 22,
            decoration: BoxDecoration(
              color: primary.withValues(alpha: 0.18),
              shape: BoxShape.circle,
            ),
            clipBehavior: Clip.antiAlias,
            child:
                logo == null
                    ? Icon(
                      Icons.business_rounded,
                      size: compact ? 12 : 14,
                      color: primary,
                    )
                    : Image.network(
                      logo,
                      fit: BoxFit.cover,
                      errorBuilder:
                          (_, __, ___) => Icon(
                            Icons.business_rounded,
                            size: compact ? 12 : 14,
                            color: primary,
                          ),
                    ),
          ),
          const SizedBox(width: 8),
          Flexible(
            child: Text(
              brand.displayName,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: AppTypography.caption.copyWith(
                color: MobileSurface.text,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
