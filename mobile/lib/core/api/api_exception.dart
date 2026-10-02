/// Thrown by [ApiClient] for any non-2xx response or network failure, carrying
/// the server's `message` (from the `{success, data, message}` envelope) when
/// available so screens can show it directly.
class ApiException implements Exception {
  final int? statusCode;
  final String message;
  final String? code;

  ApiException(this.statusCode, this.message, {this.code});

  bool get isAlreadyRecorded => statusCode == 409;
  bool get isUnauthorized => statusCode == 401;
  bool get isNetworkError => statusCode == null;
  bool get isAttendanceRequired => code == 'ATTENDANCE_REQUIRED';

  @override
  String toString() => message;
}
