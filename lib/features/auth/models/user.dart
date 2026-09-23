// Verified against the local dev backend (2026-09-11): GET
// /api/whoami?embed=identifications,roles,manager.
//
// Important: this app is an internal staff tool, not customer-facing —
// every account that can log in already has a role (App\User::
// findForPassport() rejects login for accounts without one of a fixed
// allow-list of roles: administrator, sale_manager, unit_controller,
// sale_team_leader, accountant, agent, report). So "has any role" is
// NOT a useful staff/customer distinction — every logged-in user has one.
class AppUser {
  AppUser({
    required this.id,
    required this.name,
    this.phoneNumber,
    this.email,
    this.avatarUrl,
    this.gender,
    this.birthdate,
    this.roles = const [],
  });

  factory AppUser.fromJson(Map<String, dynamic> json) => AppUser(
    id: json['id'] as int,
    name: (json['name'] ?? '').toString(),
    phoneNumber: json['phone_number']?.toString(),
    email: json['email']?.toString(),
    avatarUrl: json['avatar_url']?.toString(),
    // Backend returns "N/A" when unset and "" for a null birthdate.
    gender: json['gender']?.toString(),
    birthdate: DateTime.tryParse(json['birthdate']?.toString() ?? ''),
    roles: _parseRoles(json['roles']),
  );

  final int id;
  final String name;
  final String? phoneNumber;
  final String? email;
  final String? avatarUrl;
  final String? gender;
  final DateTime? birthdate;
  final List<String> roles;

  String? get phone => phoneNumber;
  String? get avatar => avatarUrl;

  bool hasRole(String role) => roles.contains(role);
  bool hasAnyRole(Iterable<String> candidates) => candidates.any(roles.contains);

  /// Gates Approve/Reject on Hold requests — confirmed against
  /// `Api/UnitHoldController@approve`/`@reject`: `unit_controller` only
  /// (narrower than deposits, which also allow `sale_manager`).
  bool get canApproveHold => hasRole('unit_controller');

  /// Gates Approve/Reject on Deposit requests — confirmed against
  /// `Api/UnitDepositRequestController@approve`: `unit_controller` or
  /// `sale_manager`.
  bool get canApproveDeposit => hasAnyRole(['unit_controller', 'sale_manager']);

  /// Gates Cancel on deposit requests specifically — confirmed against
  /// `Api/UnitDepositRequestController@cancel`: restricted to
  /// `unit_controller` only (narrower than approve/reject, which also
  /// allows `sale_manager` — and notably NOT available to the requesting
  /// agent at all for deposits, unlike hold/contract requests).
  bool get canCancelDeposit => hasRole('unit_controller');

  static List<String> _parseRoles(dynamic raw) {
    if (raw is! List) return const [];
    return raw
        .map((r) {
          if (r is String) return r;
          if (r is Map<String, dynamic>) {
            return (r['name'] ?? r['slug'] ?? '').toString();
          }
          return '';
        })
        .where((r) => r.isNotEmpty)
        .toList();
  }
}
