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
    final photo = img.bakeOrientation(decoded);

    final now = DateTime.now();
    final dateLine = '${now.day.toString().padLeft(2, '0')}/${now.month.toString().padLeft(2, '0')}/${now.year} '
        '${now.hour.toString().padLeft(2, '0')}:${now.minute.toString().padLeft(2, '0')}';
    final geoLine = (lat != null && lng != null)
        ? 'Lat: ${lat.toStringAsFixed(6)}  Long: ${lng.toStringAsFixed(6)}'
        : 'Location unavailable';
    final lines = [if (address != null && address.isNotEmpty) address, geoLine, dateLine];

    // The `image` package only ships fixed-size bitmap fonts (14/24/48px) --
    // to get text that's actually legible on a real 3000x4000 phone photo
    // (not a tiny 24px sliver), the band is drawn once at a fixed reference
    // width, then the whole thing is scaled up to match the real photo's
    // width. That scales the font, padding and line spacing all together,
    // the same way a fixed-DPI watermark app's overlay scales to any photo size.
    const refWidth = 1080;
    const refLineHeight = 60;
    const refPadding = 24;
    final font = img.arial48;

    final refBandHeight = lines.length * refLineHeight + refPadding * 2;
    var band = img.Image(width: refWidth, height: refBandHeight);
    img.fill(band, color: img.ColorRgba8(0, 0, 0, 150));
    var y = refPadding;
    for (final line in lines) {
      band = img.drawString(band, line, font: font, x: refPadding, y: y, color: img.ColorRgb8(255, 255, 255));
      y += refLineHeight;
    }

    final scale = photo.width / refWidth;
    final resizedBand = img.copyResize(
      band,
      width: photo.width,
      height: (refBandHeight * scale).round(),
      interpolation: img.Interpolation.cubic,
    );

    final stamped = img.compositeImage(photo, resizedBand, dstX: 0, dstY: photo.height - resizedBand.height);

    await photoFile.writeAsBytes(img.encodeJpg(stamped, quality: 88), flush: true);
  } catch (_) {
    // See doc comment -- never let a stamping failure block the capture flow.
  }
}
