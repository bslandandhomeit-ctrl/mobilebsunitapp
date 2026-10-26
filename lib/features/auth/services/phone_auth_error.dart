String phoneAuthError(String code, String? message) {
  final explanation = switch (code) {
    'internal-error' => 'Phone verification is temporarily unavailable. Please contact support if the problem continues.',
    'invalid-app-credential' ||
    'app-not-authorized' ||
    'invalid-api-key' ||
    'missing-client-identifier' => 'This app could not be verified. Please contact support.',
    'operation-not-allowed' => 'Phone verification is not available for this region. Please contact support.',
    'billing-not-enabled' => 'SMS verification is currently unavailable. Please contact support.',
    'quota-exceeded' || 'too-many-requests' =>
      'SMS verification is temporarily limited. Wait before trying again.',
    'invalid-phone-number' =>
      'Enter a valid Cambodian phone number, for example 012345678.',
    'invalid-verification-code' =>
      'The verification code is incorrect. Check the SMS and try again.',
    'session-expired' =>
      'The verification code has expired. Request a new code.',
    'network-request-failed' => 'Cannot connect to the verification service. Check your internet connection and try again.',
    'captcha-check-failed' || 'web-context-cancelled' => 'App verification was not completed. Try again and complete the verification page.',
    _ => message ?? 'Phone verification failed. Please try again.',
  };
  return '$explanation ($code)';
}
