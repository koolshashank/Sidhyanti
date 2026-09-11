import 'dart:io';

import 'package:geocoding/geocoding.dart';
import 'package:image/image.dart' as img;

import '../location/location_service.dart';

/// Stamps the current address, latitude and longitude onto the bottom-left
/// of a just-captured photo, in place -- every "Take Photo" flow in the app
/// (KYC, CGT, GRT, Death proof, Disbursement) calls this right after
/// image_picker returns a camera capture, before the file is shown/uploaded.
///
/// Only meant for live camera captures, never gallery picks -- stamping an
/// old gallery photo with the *current* location would misrepresent where
/// it was actually taken, which defeats the point of the stamp.
///
/// Best-effort: any failure (no GPS fix, reverse-geocode unavailable, image
/// can't be decoded) leaves the original file untouched rather than
/// blocking the capture flow -- an unstamped photo is fine, a broken one
/// from a botched write would not be.
Future<void> stampLocationOnPhoto(File photoFile, {LocationService? locationService}) async {
  try {
    final service = locationService ?? LocationService();
    final (lat, lng) = await service.tryGetCurrentPosition();

    String? address;
    if (lat != null && lng != null) {
      try {
        final placemarks = await Geocoding().placemarkFromCoordinates(lat, lng).timeout(const Duration(seconds: 10));
        if (placemarks.isNotEmpty) {
          final p = placemarks.first;
          address = [p.subLocality, p.locality, p.administrativeArea]
              .where((s) => s != null && s.isNotEmpty)
              .join(', ');
        }
      } catch (_) {
        // Reverse geocoding depends on the device's own Geocoder backend --
        // not guaranteed available/responsive. Coordinates alone still get stamped below.
      }
    }

    final decoded = img.decodeImage(await photoFile.readAsBytes());
    if (decoded == null) return;
    var image = img.bakeOrientation(decoded);

    final now = DateTime.now();
    final dateLine = '${now.day.toString().padLeft(2, '0')}/${now.month.toString().padLeft(2, '0')}/${now.year} '
        '${now.hour.toString().padLeft(2, '0')}:${now.minute.toString().padLeft(2, '0')}';
    final geoLine = (lat != null && lng != null)
        ? 'Lat: ${lat.toStringAsFixed(6)}  Long: ${lng.toStringAsFixed(6)}'
        : 'Location unavailable';

    final lines = [if (address != null && address.isNotEmpty) address, geoLine, dateLine];

    final font = img.arial24;
    const lineHeight = 28;
    const padding = 14;
    final bandHeight = lines.length * lineHeight + padding * 2;
    final bandTop = (image.height - bandHeight).clamp(0, image.height);

    image = img.fillRect(
      image,
      x1: 0,
      y1: bandTop,
      x2: image.width,
      y2: image.height,
      color: img.ColorRgba8(0, 0, 0, 140),
    );

    var y = bandTop + padding;
    for (final line in lines) {
      image = img.drawString(image, line, font: font, x: padding, y: y, color: img.ColorRgb8(255, 255, 255));
      y += lineHeight;
    }

    await photoFile.writeAsBytes(img.encodeJpg(image, quality: 85), flush: true);
  } catch (_) {
    // See doc comment -- never let a stamping failure block the capture flow.
  }
}
