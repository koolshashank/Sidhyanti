import 'package:dio/dio.dart';

import '../auth/token_storage.dart';
import 'api_exception.dart';
import 'endpoints.dart';

/// Thin wrapper around Dio that: (1) unwraps the API's
/// `{success, data, message}` envelope into either the `data` payload or an
/// [ApiException], and (2) transparently refreshes an expired access token
/// on a 401 and retries the original request once.
///
/// [onSessionExpired] is invoked when the refresh token itself is no longer
/// valid -- the caller (AuthState) uses it to sign the user out and route to
/// the login screen, without ApiClient needing to depend on AuthState.
class ApiClient {
  final TokenStorage _tokenStorage;
  void Function()? onSessionExpired;

  String? _accessToken;
  Future<bool>? _refreshInFlight;

  late final Dio _dio;
  late final Dio _refreshDio; // no interceptors, used only for /auth/refresh to avoid recursion

  ApiClient(this._tokenStorage) {
    _dio = Dio(BaseOptions(baseUrl: apiBaseUrl, connectTimeout: const Duration(seconds: 15)));
    _refreshDio = Dio(BaseOptions(baseUrl: apiBaseUrl, connectTimeout: const Duration(seconds: 15)));

    _dio.interceptors.add(InterceptorsWrapper(
      onRequest: (options, handler) {
        if (_accessToken != null) {
          options.headers['Authorization'] = 'Bearer $_accessToken';
        }
        handler.next(options);
      },
      onError: (error, handler) async {
        final isAuthRoute = error.requestOptions.path == Endpoints.login || error.requestOptions.path == Endpoints.refresh;
        if (error.response?.statusCode == 401 && !isAuthRoute) {
          final refreshed = await _refreshAccessToken();
          if (refreshed) {
            try {
              final response = await _dio.fetch(error.requestOptions);
              return handler.resolve(response);
            } on DioException catch (retryError) {
              return handler.next(retryError);
            }
          }
          onSessionExpired?.call();
        }
        handler.next(error);
      },
    ));
  }

  void setAccessToken(String? token) {
    _accessToken = token;
  }

  Future<bool> _refreshAccessToken() {
    // Coalesce concurrent 401s behind a single in-flight refresh call.
    return _refreshInFlight ??= _doRefresh().whenComplete(() => _refreshInFlight = null);
  }

  Future<bool> _doRefresh() async {
    final refreshToken = await _tokenStorage.readRefreshToken();
    if (refreshToken == null) return false;
    try {
      final response = await _refreshDio.post(Endpoints.refresh, data: {'refresh_token': refreshToken});
      final body = response.data as Map<String, dynamic>;
      if (body['success'] != true) return false;
      final newAccessToken = (body['data'] as Map<String, dynamic>)['access_token'] as String;
      _accessToken = newAccessToken;
      await _tokenStorage.updateAccessToken(newAccessToken);
      return true;
    } on DioException {
      return false;
    }
  }

  Future<Map<String, dynamic>> get(String path, {Map<String, dynamic>? query}) =>
      _unwrap(_dio.get(path, queryParameters: query));

  Future<Map<String, dynamic>> post(String path, {Map<String, dynamic>? data}) =>
      _unwrap(_dio.post(path, data: data));

  Future<Map<String, dynamic>> postMultipart(String path, FormData data) =>
      _unwrap(_dio.post(path, data: data));

  Future<Map<String, dynamic>> _unwrap(Future<Response> request) async {
    try {
      final response = await request;
      final body = response.data as Map<String, dynamic>;
      if (body['success'] == true) {
        return (body['data'] as Map<String, dynamic>?) ?? {};
      }
      throw ApiException(response.statusCode, body['message'] as String? ?? 'Request failed');
    } on DioException catch (e) {
      if (e.response != null) {
        final body = e.response!.data;
        final message = body is Map && body['message'] != null ? body['message'] as String : 'Request failed';
        throw ApiException(e.response!.statusCode, message);
      }
      throw ApiException(null, 'Network error -- check your connection and try again.');
    }
  }
}
