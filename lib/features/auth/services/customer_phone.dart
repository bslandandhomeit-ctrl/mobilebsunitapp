/// The backend stores Cambodian numbers in local format; Firebase uses E.164.
String? customerPhone(String input) {
  final phone = input.replaceAll(RegExp(r'[\s()-]'), '');
  final local = phone.startsWith('+855') ? '0${phone.substring(4)}' : phone;
  return RegExp(r'^0[1-9]\d{7,8}$').hasMatch(local) ? local : null;
}

String firebaseCustomerPhone(String local) => '+855${local.substring(1)}';
