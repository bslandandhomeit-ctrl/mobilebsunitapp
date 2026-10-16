// ignore: unused_import
import 'package:intl/intl.dart' as intl;

import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for English (`en`).
class AppLocalizationsEn extends AppLocalizations {
  AppLocalizationsEn([String locale = 'en']) : super(locale);

  @override
  String get appTitle => 'BS Units';

  @override
  String get home => 'Home';

  @override
  String get more => 'More';

  @override
  String get projects => 'Projects';

  @override
  String get units => 'Units';

  @override
  String get unitTypes => 'Unit Types';

  @override
  String get searchByUnitCode => 'Search by unit code…';

  @override
  String get loadMore => 'Load More';

  @override
  String get noProjectsFound => 'No projects yet.';

  @override
  String get noUnitsFound => 'No units found.';

  @override
  String get phoneNumber => 'Phone number';

  @override
  String get password => 'Password';

  @override
  String get logIn => 'Log in';

  @override
  String get forgotPassword => 'Forgot password?';

  @override
  String get pleaseEnterPhoneAndPassword =>
      'Please enter your phone number and password.';

  @override
  String get resetPassword => 'Reset Password';

  @override
  String get enterPhoneForCode =>
      'Enter your phone number to receive a verification code by SMS.';

  @override
  String get sendCode => 'Send Code';

  @override
  String enterCodeSentTo(String phone) {
    return 'Enter the code sent to $phone.';
  }

  @override
  String get verificationCode => 'Verification code';

  @override
  String get verify => 'Verify';

  @override
  String get enterNewPassword => 'Enter your new password.';

  @override
  String get newPassword => 'New password';

  @override
  String get changePassword => 'Change Password';

  @override
  String get currentPassword => 'Current password';

  @override
  String get newPasswordMinChars => 'New password (min. 6 characters)';

  @override
  String get profile => 'Profile';

  @override
  String get name => 'Name';

  @override
  String get email => 'Email';

  @override
  String get gender => 'Gender';

  @override
  String get male => 'Male';

  @override
  String get female => 'Female';

  @override
  String get birthdate => 'Birthdate';

  @override
  String get notSet => 'Not set';

  @override
  String get save => 'Save';

  @override
  String get members => 'Members';

  @override
  String get noMembersFound => 'No members yet.';

  @override
  String get notifications => 'Notifications';

  @override
  String get noNotificationsFound => 'No notifications yet.';

  @override
  String get announcements => 'Announcements';

  @override
  String get noPostsFound => 'No posts yet.';

  @override
  String get holdRequests => 'Hold Requests';

  @override
  String get depositRequests => 'Deposit Requests';

  @override
  String get contractRequests => 'Contract Requests';

  @override
  String get noHoldRequestsFound => 'No hold requests found.';

  @override
  String get noDepositRequestsFound => 'No deposit requests found.';

  @override
  String get noContractRequestsFound => 'No contract requests found.';

  @override
  String get requestHold => 'Request Hold';

  @override
  String get convertToDeposit => 'Convert to Deposit';

  @override
  String get requestContract => 'Request Contract';

  @override
  String get changeUnitTerms => 'Change unit / terms';

  @override
  String get approve => 'Approve';

  @override
  String get reject => 'Reject';

  @override
  String get cancel => 'Cancel';

  @override
  String get submitHoldRequest => 'Submit Hold Request';

  @override
  String get submitDepositRequest => 'Submit Deposit Request';

  @override
  String get submitContractRequest => 'Submit Contract Request';

  @override
  String holdDuration(int days) {
    String _temp0 = intl.Intl.pluralLogic(
      days,
      locale: localeName,
      other: 's',
      one: '',
    );
    return 'Hold duration: $days day$_temp0';
  }

  @override
  String get unitStatus => 'Status';

  @override
  String get noActionsAvailable =>
      'No actions available for this unit right now.';

  @override
  String get comments => 'Comments';

  @override
  String get addComment => 'Add a comment…';

  @override
  String get noCommentsYet => 'No comments yet.';

  @override
  String get customer => 'Customer';

  @override
  String get unit => 'Unit';

  @override
  String get depositAndDiscount => 'Deposit & Discount';

  @override
  String get paymentOption => 'Payment Option';

  @override
  String get remark => 'Remark (optional)';

  @override
  String get viewContract => 'View Contract';

  @override
  String get requestCancelReason => 'Reason';

  @override
  String get loanCalculator => 'Loan Calculator';

  @override
  String get supportCenter => 'Support Center';

  @override
  String get aboutApp => 'About BS Units';

  @override
  String get logOut => 'Log out';

  @override
  String get search => 'Search';

  @override
  String get somethingWentWrong => 'Something went wrong. Please try again.';

  @override
  String get cannotReachServer =>
      'Cannot reach the server. Check your connection.';

  @override
  String get updateRequired => 'Update Required';

  @override
  String get update => 'Update';

  @override
  String get retry => 'Retry';

  @override
  String get unitsByStatus => 'Units by status';

  @override
  String get attachPhotos => 'Attach photos';

  @override
  String get error => 'Error';

  @override
  String get failedToPostComment => 'Failed to post comment.';

  @override
  String get depositInProgressHint =>
      'This unit has a deposit in progress. Once it\'s approved, request the contract from My Requests > Deposit Requests.';

  @override
  String get passwordChanged => 'Password changed';

  @override
  String get passwordChangedMessage => 'Your password has been updated.';

  @override
  String get profileUpdated => 'Profile updated';

  @override
  String get profileUpdatedMessage => 'Your profile has been saved.';

  @override
  String get passwordReset => 'Password reset';

  @override
  String get passwordResetMessage =>
      'You can now log in with your new password.';

  @override
  String get notification => 'Notification';

  @override
  String get post => 'Post';

  @override
  String get failedToLoadPost => 'Failed to load post.';

  @override
  String get reasonRequired => 'Reason (required)';

  @override
  String get reasonOptional => 'Reason (optional)';

  @override
  String get back => 'Back';

  @override
  String get confirm => 'Confirm';

  @override
  String get unknownStatus => 'Unknown status';

  @override
  String unitNumber(String id) {
    return 'Unit #$id';
  }

  @override
  String get cancelHoldRequest => 'Cancel hold request';

  @override
  String get cancelDepositRequest => 'Cancel deposit request';

  @override
  String get cancelContractRequest => 'Cancel contract request';

  @override
  String get rejectHoldRequest => 'Reject hold request';

  @override
  String get rejectDepositRequest => 'Reject deposit request';

  @override
  String get rejectContractRequest => 'Reject contract request';

  @override
  String get contract => 'Contract';

  @override
  String get holdRequested => 'Hold requested';

  @override
  String holdRequestedMessage(String unit) {
    return 'Your hold request for $unit was submitted.';
  }

  @override
  String get holdSubmitDescription =>
      'Submitting this request will place a temporary hold on this unit pending approval.';

  @override
  String get requestDeposit => 'Request Deposit';

  @override
  String listedPrice(String price) {
    return 'Listed price: $price';
  }

  @override
  String get customerName => 'Customer name';

  @override
  String get customerPhone => 'Customer phone';

  @override
  String get addSecondCustomer => 'Add a second customer (co-buyer)';

  @override
  String get secondCustomerName => 'Second customer name';

  @override
  String get secondCustomerPhone => 'Second customer phone';

  @override
  String get depositAmount => 'Deposit amount';

  @override
  String get otherDiscountAllowance => 'Other discount allowance';

  @override
  String get useCustomLoanTerms =>
      'Use custom loan terms instead of a preset option';

  @override
  String get loanDurationYears => 'Loan duration (years)';

  @override
  String get annualInterestRate => 'Annual interest rate (%)';

  @override
  String get specialDiscountPercent => 'Special discount (%)';

  @override
  String get hasFirstPayment => 'Has a first payment';

  @override
  String get firstPaymentDurationMonths => 'First payment duration (months)';

  @override
  String get firstPaymentPercentage => 'First payment percentage (%)';

  @override
  String get depositRequested => 'Deposit requested';

  @override
  String depositRequestedMessage(String unit) {
    return 'Your deposit request for $unit was submitted.';
  }

  @override
  String get requestContractTitle => 'Request Contract';

  @override
  String get depositRequestLabel => 'Deposit Request';

  @override
  String depositRequestSummary(String id, String unitId) {
    return '#$id · Unit #$unitId';
  }

  @override
  String get signedDate => 'Signed date';

  @override
  String get firstPaymentDate => 'First payment date';

  @override
  String get attachSignedContractHint =>
      'Attach at least one photo of the signed contract.';

  @override
  String get attachmentRequiredError =>
      'At least one attachment (e.g. signed contract page) is required.';

  @override
  String get gallery => 'Gallery';

  @override
  String get camera => 'Camera';

  @override
  String get contractRequested => 'Contract requested';

  @override
  String get contractRequestedMessage => 'Your contract request was submitted.';

  @override
  String get changeUnitTermsTitle => 'Change Unit / Terms';

  @override
  String get currentUnit => 'Current unit';

  @override
  String get searchDifferentUnitHint =>
      'Search for a different unit (leave blank to keep current)';

  @override
  String newUnitLabel(String name) {
    return 'New unit: $name';
  }

  @override
  String get updated => 'Updated';

  @override
  String get depositRequestUpdatedMessage => 'The deposit request was updated.';

  @override
  String get saveChanges => 'Save Changes';

  @override
  String get paymentOptions => 'Payment Options';

  @override
  String get noPaymentOptionsForUnitType =>
      'No payment options for this unit type.';

  @override
  String depositAmountShort(String amount) {
    return 'Deposit: $amount';
  }

  @override
  String loanDurationYearsShort(String years) {
    return '$years yrs';
  }

  @override
  String interestPercentShort(String percent) {
    return '$percent% interest';
  }

  @override
  String unitsByStatusTitle(String project) {
    return '$project — Units by Status';
  }

  @override
  String get failedToLoadUnits => 'Failed to load units.';

  @override
  String get searchUnitCodeHint => 'Search unit code…';

  @override
  String get startTypingUnitCode => 'Start typing a unit code.';

  @override
  String get loanAmount => 'Loan amount';

  @override
  String get numberOfMonths => 'Number of months';

  @override
  String get calculate => 'Calculate';

  @override
  String get monthlyPayment => 'Monthly payment';

  @override
  String get totalPayment => 'Total payment';

  @override
  String get totalInterest => 'Total interest';

  @override
  String get telegramGroup => 'Telegram Group';

  @override
  String versionLabel(String version, String build) {
    return 'Version $version ($build)';
  }

  @override
  String get failedToLoadContractPdf => 'Failed to load the contract PDF.';

  @override
  String updateRequiredMessage(String version) {
    return 'A new version ($version) is required to continue using this app.';
  }

  @override
  String get documentScanner => 'Document Scanner';

  @override
  String get documentScannerHint =>
      'Take a photo of a document or ID card, crop it, and save it to your gallery.';

  @override
  String get takePhoto => 'Take Photo';

  @override
  String get scanAnother => 'Scan Another';

  @override
  String get done => 'Done';

  @override
  String get imageSaved => 'Image saved';

  @override
  String get imageSavedToGallery => 'The image was saved to your gallery.';

  @override
  String get failedToSaveImage => 'Failed to save the image.';

  @override
  String get cropImage => 'Crop Image';

  @override
  String get showPassword => 'Show password';

  @override
  String get hidePassword => 'Hide password';

  @override
  String get unitCode => 'Unit Code';

  @override
  String get project => 'Project';

  @override
  String get unitType => 'Unit Type';

  @override
  String get unitPriceLabel => 'Unit Price (\$)';

  @override
  String get houseSizeLabel => 'House size';

  @override
  String get landSizeLabel => 'Land size';

  @override
  String get bedroomsLabel => 'Bedrooms';

  @override
  String get bathroomsLabel => 'Bathrooms';

  @override
  String get livingRoomsLabel => 'Living rooms';

  @override
  String get kitchensLabel => 'Kitchens';

  @override
  String get swimmingPoolsLabel => 'Swimming pools';

  @override
  String get unitInformationTitle => 'Unit\'s Information';

  @override
  String get contractPriceNote =>
      'This is the contractual price before other discounts.';

  @override
  String get holdTabLabel => 'Hold Req';

  @override
  String get depositTabLabel => 'Deposit Req';

  @override
  String get contractTabLabel => 'Contract Req';

  @override
  String get filterTitle => 'Filters';

  @override
  String get allProjectsLabel => 'All projects';

  @override
  String get availableAndUnavailable => 'Available & unavailable';

  @override
  String get allStatusesLabel => 'All statuses';

  @override
  String get applyFiltersLabel => 'Apply filters';

  @override
  String get detailLoadError =>
      'Could not load the latest unit details. Please retry.';

  @override
  String get previewLabel => 'Preview';

  @override
  String get exteriorLabel => 'Exterior';

  @override
  String get interiorLabel => 'Interior';

  @override
  String get floorPlanLabel => 'Floor Plan';

  @override
  String get noImagesLabel => 'No images available';

  @override
  String get paymentOptionsLoadError =>
      'Payment options could not be loaded. Retry or choose Other.';

  @override
  String get requiredFieldLabel => 'This field is required.';

  @override
  String get invalidPhoneLabel =>
      'Enter a phone number starting with 0 (9–10 digits).';

  @override
  String get invalidNumberLabel => 'Enter a valid number in the allowed range.';

  @override
  String get customerInformationTitle => 'Customer\'s Information';

  @override
  String get customerOneNameLabel => 'Surname and Name (Customer 1) *';

  @override
  String get customerTwoNameLabel => 'Surname and Name (Customer 2)';

  @override
  String get customerOneGenderLabel => 'Gender (Customer 1) *';

  @override
  String get customerTwoGenderLabel => 'Gender (Customer 2)';

  @override
  String get customerPhonesTitle => 'Phone Numbers (Customer)';

  @override
  String get phoneLineOneLabel => 'Line 1 *';

  @override
  String get phoneLineTwoLabel => 'Line 2';

  @override
  String get otherDiscountDepositTitle => 'Other Discount & Deposit';

  @override
  String get otherDiscountNote =>
      'A company-approved discount stated in the contract, not an agent discount. Requires sales manager approval.';

  @override
  String get promotionalDiscountLabel => 'Promotional Discount (\$)';

  @override
  String get promotionalDiscountNote =>
      'Calculated automatically from the active promotion by the server.';

  @override
  String get noneLabel => 'None';

  @override
  String get otherLabel => 'Other';

  @override
  String get standardLabel => 'Standard';

  @override
  String get otherPaymentOptionNote =>
      'Other payment options require sales manager approval.';

  @override
  String get installmentMonthsLabel => 'Installment Duration (months)';

  @override
  String get downPaymentLabel => 'Down Payment';

  @override
  String get downPaymentAmountLabel => 'Down Payment Amount (\$)';

  @override
  String get remarkPlaceholder => 'Enter your remark…';

  @override
  String get saveLabel => 'Save';

  @override
  String get nextLabel => 'Next';

  @override
  String get yes => 'Yes';

  @override
  String get no => 'No';

  @override
  String get holdButtonLabel => 'Hold';

  @override
  String get depositButtonLabel => 'Deposit';

  @override
  String get customPaymentHint => 'Choose Other to enter custom payment terms.';

  @override
  String get holdDaysLabel => 'Days';

  @override
  String get expirationDateLabel => 'Expiration Date';

  @override
  String get holdDateLabel => 'Hold Date';

  @override
  String get requestDateLabel => 'Request Date';

  @override
  String get requestedByLabel => 'Requested by';

  @override
  String get receivedAmountLabel => 'Received Amount';

  @override
  String get salesManagerLabel => 'Sales Manager';

  @override
  String get unitControllerLabel => 'Unit Controller';

  @override
  String get floorLabel => 'Floor';

  @override
  String get holdRequestTitle => 'Hold Request';

  @override
  String get depositRequestTitle => 'Deposit Request';

  @override
  String get requestAttachmentsTitle => 'Attachments *';

  @override
  String get requestAttachmentsNote =>
      'Include: ID card, passport, or other related documents.';
}
