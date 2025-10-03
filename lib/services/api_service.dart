import 'dart:io';
import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../models/user_model.dart';
import '../models/meal_model.dart';

class ApiService {
  // Configuração de ambiente
  static const String _devUrl = 'https://pixeat.site/api/v1';
  static const String _prodUrl = 'https://pixeat.site/api/v1';
  
  // Detecta automaticamente o ambiente
  static String get baseUrl {
    const bool isProduction = bool.fromEnvironment('dart.vm.product');
    return isProduction ? _prodUrl : _devUrl;
  }
  late Dio _dio;
  String? _authToken;

  // Singleton
  static final ApiService _instance = ApiService._internal();
  factory ApiService() => _instance;
  
  ApiService._internal() {
    _dio = Dio(BaseOptions(
      baseUrl: baseUrl,
      connectTimeout: const Duration(seconds: 30),
      receiveTimeout: const Duration(seconds: 30),
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
    ));

    // Interceptor para adicionar token automaticamente
    _dio.interceptors.add(InterceptorsWrapper(
      onRequest: (options, handler) async {
        if (_authToken != null) {
          options.headers['Authorization'] = 'Bearer $_authToken';
        }
        handler.next(options);
      },
      onError: (error, handler) async {
        if (error.response?.statusCode == 401) {
          // Token expirado, fazer logout
          await _clearAuthToken();
        }
        handler.next(error);
      },
    ));

    // Carregar token salvo
    _loadAuthToken();
  }

  // Gerenciamento de Token
  Future<void> setAuthToken(String token) async {
    _authToken = token;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('auth_token', token);
  }

  Future<void> _loadAuthToken() async {
    final prefs = await SharedPreferences.getInstance();
    _authToken = prefs.getString('auth_token');
  }

  Future<void> _clearAuthToken() async {
    _authToken = null;
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('auth_token');
  }

  bool get isAuthenticated => _authToken != null;

  // AUTH ENDPOINTS
  Future<Map<String, dynamic>> login(String email, String password) async {
    try {
      final response = await _dio.post('/auth/login', data: {
        'email': email,
        'password': password,
      });
      
      if (response.data['access_token'] != null) {
        await setAuthToken(response.data['access_token']);
      }
      
      return response.data;
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  Future<Map<String, dynamic>> register(String name, String email, String password) async {
    try {
      final response = await _dio.post('/auth/register', data: {
        'name': name,
        'email': email,
        'password': password,
        'password_confirmation': password,
      });
      
      if (response.data['access_token'] != null) {
        await setAuthToken(response.data['access_token']);
      }
      
      return response.data;
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  Future<void> logout() async {
    try {
      await _dio.post('/auth/logout');
    } catch (e) {
      // Ignorar erros de logout
    } finally {
      await _clearAuthToken();
    }
  }

  Future<Map<String, dynamic>> refreshToken() async {
    try {
      final response = await _dio.post('/auth/refresh');
      
      if (response.data['access_token'] != null) {
        await setAuthToken(response.data['access_token']);
      }
      
      return response.data;
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  // USER ENDPOINTS
  Future<UserModel> getProfile() async {
    try {
      final response = await _dio.get('/user/profile');
      return UserModel.fromJson(response.data['data']);
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  Future<UserModel> updateProfile(UserModel user) async {
    try {
      final response = await _dio.put('/user/profile', data: user.toJson());
      return UserModel.fromJson(response.data['data']);
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  Future<void> deleteAccount() async {
    try {
      await _dio.delete('/user/account');
      await _clearAuthToken();
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  // MEAL ENDPOINTS
  Future<List<MealModel>> getMeals({
    DateTime? date,
    DateTime? startDate,
    DateTime? endDate,
    int? limit,
    int page = 1,
  }) async {
    try {
      Map<String, dynamic> queryParams = {'page': page};
      
      if (date != null) {
        queryParams['date'] = date.toIso8601String().split('T')[0];
      }
      if (startDate != null) {
        queryParams['start_date'] = startDate.toIso8601String().split('T')[0];
      }
      if (endDate != null) {
        queryParams['end_date'] = endDate.toIso8601String().split('T')[0];
      }
      if (limit != null) {
        queryParams['limit'] = limit;
      }

      final response = await _dio.get('/meals', queryParameters: queryParams);
      return (response.data['data'] as List)
          .map((json) => MealModel.fromJson(json))
          .toList();
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  Future<MealModel> getMeal(String mealId) async {
    try {
      final response = await _dio.get('/meals/$mealId');
      return MealModel.fromJson(response.data['data']);
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  Future<MealModel> createMeal(MealModel meal) async {
    try {
      final response = await _dio.post('/meals', data: meal.toJson());
      return MealModel.fromJson(response.data['data']);
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  Future<MealModel> updateMeal(MealModel meal) async {
    try {
      final response = await _dio.put('/meals/${meal.id}', data: meal.toJson());
      return MealModel.fromJson(response.data['data']);
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  Future<void> deleteMeal(String mealId) async {
    try {
      await _dio.delete('/meals/$mealId');
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  // AI ANALYSIS ENDPOINT
  Future<AIAnalysisResult> analyzeImage(File imageFile) async {
    try {
      String fileName = imageFile.path.split('/').last;
      FormData formData = FormData.fromMap({
        'image': await MultipartFile.fromFile(
          imageFile.path, 
          filename: fileName,
        ),
      });

      final response = await _dio.post('/ai/analyze', data: formData);
      return AIAnalysisResult.fromJson(response.data['data']);
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  // STATISTICS ENDPOINTS
  Future<Map<String, dynamic>> getDailyStats(DateTime date) async {
    try {
      final response = await _dio.get('/stats/daily', queryParameters: {
        'date': date.toIso8601String().split('T')[0],
      });
      return response.data['data'];
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  Future<Map<String, dynamic>> getWeeklyStats(DateTime weekStart) async {
    try {
      final response = await _dio.get('/stats/weekly', queryParameters: {
        'week_start': weekStart.toIso8601String().split('T')[0],
      });
      return response.data['data'];
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  Future<Map<String, dynamic>> getMonthlyStats(int year, int month) async {
    try {
      final response = await _dio.get('/stats/monthly', queryParameters: {
        'year': year,
        'month': month,
      });
      return response.data['data'];
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  // SUBSCRIPTION ENDPOINTS
  Future<Map<String, dynamic>> getSubscriptionStatus() async {
    try {
      final response = await _dio.get('/subscription/status');
      return response.data['data'];
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  Future<Map<String, dynamic>> createSubscription(String planId) async {
    try {
      final response = await _dio.post('/subscription/create', data: {
        'plan_id': planId,
      });
      return response.data['data'];
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  Future<void> cancelSubscription() async {
    try {
      await _dio.put('/subscription/cancel');
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  // SETTINGS ENDPOINTS
  Future<Map<String, dynamic>> getSettings() async {
    try {
      final response = await _dio.get('/user/settings');
      return response.data['data'];
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  Future<void> updateSettings(Map<String, dynamic> settings) async {
    try {
      await _dio.put('/user/settings', data: settings);
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  // Error handling
  String _handleError(DioException error) {
    switch (error.type) {
      case DioExceptionType.connectionTimeout:
      case DioExceptionType.sendTimeout:
      case DioExceptionType.receiveTimeout:
        return 'Timeout: Verifique sua conexão com a internet';
      
      case DioExceptionType.badResponse:
        final statusCode = error.response?.statusCode;
        final message = error.response?.data['message'] ?? 'Erro desconhecido';
        
        switch (statusCode) {
          case 400:
            return 'Dados inválidos: $message';
          case 401:
            return 'Não autorizado: Faça login novamente';
          case 403:
            return 'Acesso negado: $message';
          case 404:
            return 'Recurso não encontrado';
          case 422:
            // Erros de validação
            if (error.response?.data['errors'] != null) {
              final errors = error.response!.data['errors'] as Map<String, dynamic>;
              final firstError = errors.values.first;
              if (firstError is List && firstError.isNotEmpty) {
                return firstError.first.toString();
              }
            }
            return 'Dados inválidos: $message';
          case 429:
            return 'Muitas tentativas: Tente novamente mais tarde';
          case 500:
            return 'Erro interno do servidor';
          default:
            return 'Erro: $message';
        }
      
      case DioExceptionType.cancel:
        return 'Requisição cancelada';
      
      case DioExceptionType.unknown:
        if (error.error is SocketException) {
          return 'Sem conexão com a internet';
        }
        return 'Erro de conexão: Verifique sua internet';
      
      default:
        return 'Erro desconhecido';
    }
  }

  // Health check
  Future<bool> isServerHealthy() async {
    try {
      final response = await _dio.get('/health');
      return response.statusCode == 200;
    } catch (e) {
      return false;
    }
  }

  // Upload de imagem (para perfil, etc)
  Future<String> uploadImage(File imageFile, {String folder = 'general'}) async {
    try {
      String fileName = imageFile.path.split('/').last;
      FormData formData = FormData.fromMap({
        'image': await MultipartFile.fromFile(
          imageFile.path, 
          filename: fileName,
        ),
        'folder': folder,
      });

      final response = await _dio.post('/upload', data: formData);
      return response.data['data']['url'];
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }
}

// Provider do ApiService
final apiServiceProvider = Provider<ApiService>((ref) {
  return ApiService();
});
