import 'dart:io';

import 'package:camera/camera.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_mlkit_face_detection/google_mlkit_face_detection.dart';

/// In-app live-camera capture that auto-clicks once a face is detected and
/// held steady in frame for ~1.4s -- so the FO doesn't have to tap a
/// shutter button themselves while holding the phone up to the customer.
/// Falls back to a manual "Capture manually" button at all times so a bad
/// light/angle that confuses face detection never leaves the FO stuck.
class FaceAutoCaptureScreen extends StatefulWidget {
  const FaceAutoCaptureScreen({super.key});

  @override
  State<FaceAutoCaptureScreen> createState() => _FaceAutoCaptureScreenState();
}

enum _Stage { initializing, scanning, holding, capturing, error }

class _FaceAutoCaptureScreenState extends State<FaceAutoCaptureScreen> {
  CameraController? _controller;
  FaceDetector? _detector;
  _Stage _stage = _Stage.initializing;
  String? _error;
  bool _busy = false;
  DateTime? _steadySince;

  static const _holdDuration = Duration(milliseconds: 1400);

  @override
  void initState() {
    super.initState();
    SystemChrome.setPreferredOrientations([DeviceOrientation.portraitUp]);
    _init();
  }

  Future<void> _init() async {
    try {
      final cameras = await availableCameras();
      if (cameras.isEmpty) {
        setState(() {
          _stage = _Stage.error;
          _error = 'No camera found on this device.';
        });
        return;
      }
      final camera = cameras.firstWhere(
        (c) => c.lensDirection == CameraLensDirection.back,
        orElse: () => cameras.first,
      );
      final controller = CameraController(
        camera,
        ResolutionPreset.medium,
        enableAudio: false,
        imageFormatGroup: Platform.isIOS ? ImageFormatGroup.bgra8888 : ImageFormatGroup.nv21,
      );
      await controller.initialize();
      if (!mounted) {
        await controller.dispose();
        return;
      }
      _detector = FaceDetector(options: FaceDetectorOptions(performanceMode: FaceDetectorMode.fast));
      _controller = controller;
      setState(() => _stage = _Stage.scanning);
      await controller.startImageStream(_onFrame);
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _stage = _Stage.error;
        _error = 'Could not start the camera.';
      });
    }
  }

  void _onFrame(CameraImage image) {
    if (_busy || _stage == _Stage.capturing || _controller == null || _detector == null) return;
    _busy = true;
    _processFrame(image).whenComplete(() => _busy = false);
  }

  Future<void> _processFrame(CameraImage image) async {
    final inputImage = _toInputImage(image);
    if (inputImage == null) return;
    List<Face> faces;
    try {
      faces = await _detector!.processImage(inputImage);
    } catch (_) {
      return;
    }
    if (!mounted || _stage == _Stage.capturing) return;

    if (faces.isNotEmpty) {
      _steadySince ??= DateTime.now();
      if (DateTime.now().difference(_steadySince!) >= _holdDuration) {
        await _capture();
        return;
      }
      if (_stage != _Stage.holding) setState(() => _stage = _Stage.holding);
    } else {
      _steadySince = null;
      if (_stage != _Stage.scanning) setState(() => _stage = _Stage.scanning);
    }
  }

  InputImage? _toInputImage(CameraImage image) {
    final rotation = InputImageRotationValue.fromRawValue(_controller!.description.sensorOrientation) ?? InputImageRotation.rotation0deg;
    final format = Platform.isIOS ? InputImageFormat.bgra8888 : InputImageFormat.nv21;
    final plane = image.planes.first;
    return InputImage.fromBytes(
      bytes: plane.bytes,
      metadata: InputImageMetadata(
        size: Size(image.width.toDouble(), image.height.toDouble()),
        rotation: rotation,
        format: format,
        bytesPerRow: plane.bytesPerRow,
      ),
    );
  }

  Future<void> _capture() async {
    final controller = _controller;
    if (controller == null || _stage == _Stage.capturing) return;
    setState(() => _stage = _Stage.capturing);
    try {
      if (controller.value.isStreamingImages) {
        await controller.stopImageStream();
      }
      final file = await controller.takePicture();
      if (!mounted) return;
      Navigator.of(context).pop(file);
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _stage = _Stage.error;
        _error = 'Capture failed. Please try again.';
      });
    }
  }

  @override
  void dispose() {
    SystemChrome.setPreferredOrientations(DeviceOrientation.values);
    _controller?.dispose();
    _detector?.close();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.black,
        foregroundColor: Colors.white,
        elevation: 0,
        title: const Text('Smart Capture'),
      ),
      body: _buildBody(),
    );
  }

  Widget _buildBody() {
    if (_stage == _Stage.error) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.error_outline_rounded, color: Colors.white70, size: 40),
              const SizedBox(height: 12),
              Text(_error ?? 'Something went wrong.', style: const TextStyle(color: Colors.white70), textAlign: TextAlign.center),
              const SizedBox(height: 16),
              OutlinedButton(
                style: OutlinedButton.styleFrom(foregroundColor: Colors.white, side: const BorderSide(color: Colors.white54)),
                onPressed: () => Navigator.of(context).pop(),
                child: const Text('Go Back'),
              ),
            ],
          ),
        ),
      );
    }
    final controller = _controller;
    if (controller == null || !controller.value.isInitialized) {
      return const Center(child: CircularProgressIndicator(color: Colors.white));
    }
    return Stack(
      fit: StackFit.expand,
      children: [
        Center(child: CameraPreview(controller)),
        Align(
          alignment: const Alignment(0, -0.15),
          child: Container(
            width: 230,
            height: 290,
            decoration: BoxDecoration(
              border: Border.all(
                color: _stage == _Stage.holding ? const Color(0xFF2FBF87) : Colors.white70,
                width: 3,
              ),
              borderRadius: BorderRadius.circular(140),
            ),
          ),
        ),
        Positioned(
          left: 0,
          right: 0,
          bottom: 48,
          child: Column(
            children: [
              Text(
                _statusText,
                style: const TextStyle(color: Colors.white, fontSize: 15, fontWeight: FontWeight.w600),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 18),
              TextButton.icon(
                onPressed: _capture,
                icon: const Icon(Icons.camera_alt_rounded, color: Colors.white70),
                label: const Text('Capture manually', style: TextStyle(color: Colors.white70)),
              ),
            ],
          ),
        ),
      ],
    );
  }

  String get _statusText {
    switch (_stage) {
      case _Stage.scanning:
        return 'Position the face inside the frame';
      case _Stage.holding:
        return 'Hold still...';
      case _Stage.capturing:
        return 'Capturing...';
      default:
        return '';
    }
  }
}
