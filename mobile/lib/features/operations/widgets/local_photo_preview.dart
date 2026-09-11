import 'dart:io';

import 'package:flutter/material.dart';

/// Full-screen, pinch-to-zoom preview of a just-captured local photo -- lets
/// the user actually see whether the location/lat-long/date-time stamp (see
/// core/utils/geo_photo_stamp.dart) landed on it before submitting, instead
/// of only ever seeing a 44-52px thumbnail too small to tell.
Future<void> showLocalPhotoPreview(BuildContext context, File file) {
  return Navigator.of(context).push(
    MaterialPageRoute(
      fullscreenDialog: true,
      builder: (context) => Scaffold(
        backgroundColor: Colors.black,
        appBar: AppBar(
          backgroundColor: Colors.black,
          foregroundColor: Colors.white,
          elevation: 0,
        ),
        body: Center(
          child: InteractiveViewer(
            minScale: 1,
            maxScale: 4,
            child: Image.file(file),
          ),
        ),
      ),
    ),
  );
}
