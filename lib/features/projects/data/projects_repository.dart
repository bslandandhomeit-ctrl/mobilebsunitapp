import '../../../core/constants/api_constants.dart';
import '../../../core/models/paginated_response.dart';
import '../../../core/network/api_client.dart';
import '../models/project.dart';
import '../models/unit.dart';
import '../models/unit_type.dart';

class ProjectsRepository {
  final ApiClient _client = ApiClient.instance;

  Future<PaginatedResponse<Project>> getProjects() async {
    final response = await _client.get<Map<String, dynamic>>(
      ApiConstants.projectsPath,
      queryParameters: {
        'embed': 'createdBy,unitTypes,unitTypes.activeDiscountPromotions',
      },
    );
    return PaginatedResponse.fromJson(response.data!, Project.fromJson);
  }

  Future<Unit> getUnit(int id) async {
    final response = await _client.get<Map<String, dynamic>>(
      '${ApiConstants.unitsPath}/$id',
      queryParameters: {
        'embed':
            'unitType.project,unitType.media,unitType.activeDiscountPromotions',
      },
    );
    return Unit.fromJson(PaginatedResponse.unwrap(response.data));
  }

  Future<PaginatedResponse<Unit>> browseUnits({
    int? projectId,
    int? unitTypeId,
    String? status,
    String? term,
    int page = 1,
  }) async {
    final response = await _client.get<Map<String, dynamic>>(
      ApiConstants.unitsPath,
      queryParameters: {
        'project_id': ?projectId,
        'unit_type_id': ?unitTypeId,
        'status': ?status,
        'term': ?term,
        'page': page,
        'per_page': 20,
        'embed': 'unitType.project',
      },
    );
    return PaginatedResponse.fromJson(response.data!, Unit.fromJson);
  }

  Future<Project> getProject(int id) async {
    final response = await _client.get<Map<String, dynamic>>(
      '${ApiConstants.projectsPath}/$id',
      queryParameters: {'embed': 'createdBy,unitTypes'},
    );
    return Project.fromJson(PaginatedResponse.unwrap(response.data));
  }

  Future<PaginatedResponse<Unit>> getUnitsForProject(
    int projectId, {
    List<String> statuses = const [
      'AVAILABLE',
      'UNAVAILABLE',
      'HOLD',
      'DEPOSIT',
      'CONTRACT',
    ],
    int perPage = 20,
    int page = 1,
  }) async {
    final response = await _client.get<Map<String, dynamic>>(
      '${ApiConstants.projectsPath}/$projectId/units',
      queryParameters: {
        'status': statuses.join(','),
        'per_page': perPage,
        'page': page,
      },
    );
    return PaginatedResponse.fromJson(response.data!, Unit.fromJson);
  }

  /// Global unit search by code — verified against `UnitController@all`
  /// (2026-09-11): `term` does a `code LIKE %term%` across all published
  /// projects. This is what the original app's `AutoSuggestionActivity`
  /// called.
  Future<PaginatedResponse<Unit>> searchUnits(
    String term, {
    int perPage = 20,
  }) async {
    final response = await _client.get<Map<String, dynamic>>(
      ApiConstants.unitsPath,
      queryParameters: {'term': term, 'per_page': perPage},
    );
    return PaginatedResponse.fromJson(response.data!, Unit.fromJson);
  }

  Future<PaginatedResponse<UnitType>> getUnitTypesForProject(
    int projectId,
  ) async {
    final response = await _client.get<Map<String, dynamic>>(
      '${ApiConstants.projectsPath}/$projectId/unit_types',
    );
    return PaginatedResponse.fromJson(response.data!, UnitType.fromJson);
  }

  /// Verified against `ProjectController@getUnitsForFloorPlan` (2026-09-11):
  /// despite the name, this isn't a visual coordinate overlay — it's the
  /// project's units grouped by `availability_status`.
  Future<Map<String, List<Unit>>> getUnitsForFloorPlan(int projectId) async {
    final response = await _client.get<Map<String, dynamic>>(
      '${ApiConstants.projectsPath}/$projectId/units_for_plan',
    );
    final data = PaginatedResponse.unwrap(response.data);
    final unitsByStatus = data['units'] as Map<String, dynamic>? ?? {};
    return unitsByStatus.map(
      (status, list) => MapEntry(
        status,
        (list as List)
            .whereType<Map<String, dynamic>>()
            .map(Unit.fromJson)
            .toList(),
      ),
    );
  }
}
