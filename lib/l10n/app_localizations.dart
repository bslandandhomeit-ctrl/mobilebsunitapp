import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:intl/intl.dart' as intl;

import 'app_localizations_en.dart';
import 'app_localizations_km.dart';

// ignore_for_file: type=lint

/// Callers can lookup localized strings with an instance of AppLocalizations
/// returned by `AppLocalizations.of(context)`.
///
/// Applications need to include `AppLocalizations.delegate()` in their app's
/// `localizationDelegates` list, and the locales they support in the app's
/// `supportedLocales` list. For example:
///
/// ```dart
/// import 'l10n/app_localizations.dart';
///
/// return MaterialApp(
///   localizationsDelegates: AppLocalizations.localizationsDelegates,
///   supportedLocales: AppLocalizations.supportedLocales,
///   home: MyApplicationHome(),
/// );
/// ```
///
/// ## Update pubspec.yaml
///
/// Please make sure to update your pubspec.yaml to include the following
/// packages:
///
/// ```yaml
/// dependencies:
///   # Internationalization support.
///   flutter_localizations:
///     sdk: flutter
///   intl: any # Use the pinned version from flutter_localizations
///
///   # Rest of dependencies
/// ```
///
/// ## iOS Applications
///
/// iOS applications define key application metadata, including supported
/// locales, in an Info.plist file that is built into the application bundle.
/// To configure the locales supported by your app, you’ll need to edit this
/// file.
///
/// First, open your project’s ios/Runner.xcworkspace Xcode workspace file.
/// Then, in the Project Navigator, open the Info.plist file under the Runner
/// project’s Runner folder.
///
/// Next, select the Information Property List item, select Add Item from the
/// Editor menu, then select Localizations from the pop-up menu.
///
/// Select and expand the newly-created Localizations item then, for each
/// locale your application supports, add a new item and select the locale
/// you wish to add from the pop-up menu in the Value field. This list should
/// be consistent with the languages listed in the AppLocalizations.supportedLocales
/// property.
abstract class AppLocalizations {
  AppLocalizations(String locale)
    : localeName = intl.Intl.canonicalizedLocale(locale.toString());

  final String localeName;

  static AppLocalizations? of(BuildContext context) {
    return Localizations.of<AppLocalizations>(context, AppLocalizations);
  }

  static const LocalizationsDelegate<AppLocalizations> delegate =
      _AppLocalizationsDelegate();

  /// A list of this localizations delegate along with the default localizations
  /// delegates.
  ///
  /// Returns a list of localizations delegates containing this delegate along with
  /// GlobalMaterialLocalizations.delegate, GlobalCupertinoLocalizations.delegate,
  /// and GlobalWidgetsLocalizations.delegate.
  ///
  /// Additional delegates can be added by appending to this list in
  /// MaterialApp. This list does not have to be used at all if a custom list
  /// of delegates is preferred or required.
  static const List<LocalizationsDelegate<dynamic>> localizationsDelegates =
      <LocalizationsDelegate<dynamic>>[
        delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
      ];

  /// A list of this localizations delegate's supported locales.
  static const List<Locale> supportedLocales = <Locale>[
    Locale('en'),
    Locale('km'),
  ];

  /// No description provided for @appTitle.
  ///
  /// In en, this message translates to:
  /// **'BS Units'**
  String get appTitle;

  /// No description provided for @home.
  ///
  /// In en, this message translates to:
  /// **'Home'**
  String get home;

  /// No description provided for @more.
  ///
  /// In en, this message translates to:
  /// **'More'**
  String get more;

  /// No description provided for @projects.
  ///
  /// In en, this message translates to:
  /// **'Projects'**
  String get projects;

  /// No description provided for @units.
  ///
  /// In en, this message translates to:
  /// **'Units'**
  String get units;

  /// No description provided for @unitTypes.
  ///
  /// In en, this message translates to:
  /// **'Unit Types'**
  String get unitTypes;

  /// No description provided for @searchByUnitCode.
  ///
  /// In en, this message translates to:
  /// **'Search by unit code…'**
  String get searchByUnitCode;

  /// No description provided for @loadMore.
  ///
  /// In en, this message translates to:
  /// **'Load More'**
  String get loadMore;

  /// No description provided for @noProjectsFound.
  ///
  /// In en, this message translates to:
  /// **'No projects yet.'**
  String get noProjectsFound;

  /// No description provided for @noUnitsFound.
  ///
  /// In en, this message translates to:
  /// **'No units found.'**
  String get noUnitsFound;

  /// No description provided for @phoneNumber.
  ///
  /// In en, this message translates to:
  /// **'Phone number'**
  String get phoneNumber;

  /// No description provided for @password.
  ///
  /// In en, this message translates to:
  /// **'Password'**
  String get password;

  /// No description provided for @logIn.
  ///
  /// In en, this message translates to:
  /// **'Log in'**
  String get logIn;

  /// No description provided for @forgotPassword.
  ///
  /// In en, this message translates to:
  /// **'Forgot password?'**
  String get forgotPassword;

  /// No description provided for @pleaseEnterPhoneAndPassword.
  ///
  /// In en, this message translates to:
  /// **'Please enter your phone number and password.'**
  String get pleaseEnterPhoneAndPassword;

  /// No description provided for @resetPassword.
  ///
  /// In en, this message translates to:
  /// **'Reset Password'**
  String get resetPassword;

  /// No description provided for @enterPhoneForCode.
  ///
  /// In en, this message translates to:
  /// **'Enter your phone number to receive a verification code by SMS.'**
  String get enterPhoneForCode;

  /// No description provided for @sendCode.
  ///
  /// In en, this message translates to:
  /// **'Send Code'**
  String get sendCode;

  /// No description provided for @enterCodeSentTo.
  ///
  /// In en, this message translates to:
  /// **'Enter the code sent to {phone}.'**
  String enterCodeSentTo(String phone);

  /// No description provided for @verificationCode.
  ///
  /// In en, this message translates to:
  /// **'Verification code'**
  String get verificationCode;

  /// No description provided for @verify.
  ///
  /// In en, this message translates to:
  /// **'Verify'**
  String get verify;

  /// No description provided for @enterNewPassword.
  ///
  /// In en, this message translates to:
  /// **'Enter your new password.'**
  String get enterNewPassword;

  /// No description provided for @newPassword.
  ///
  /// In en, this message translates to:
  /// **'New password'**
  String get newPassword;

  /// No description provided for @changePassword.
  ///
  /// In en, this message translates to:
  /// **'Change Password'**
  String get changePassword;

  /// No description provided for @currentPassword.
  ///
  /// In en, this message translates to:
  /// **'Current password'**
  String get currentPassword;

  /// No description provided for @newPasswordMinChars.
  ///
  /// In en, this message translates to:
  /// **'New password (min. 6 characters)'**
  String get newPasswordMinChars;

  /// No description provided for @profile.
  ///
  /// In en, this message translates to:
  /// **'Profile'**
  String get profile;

  /// No description provided for @name.
  ///
  /// In en, this message translates to:
  /// **'Name'**
  String get name;

  /// No description provided for @email.
  ///
  /// In en, this message translates to:
  /// **'Email'**
  String get email;

  /// No description provided for @gender.
  ///
  /// In en, this message translates to:
  /// **'Gender'**
  String get gender;

  /// No description provided for @male.
  ///
  /// In en, this message translates to:
  /// **'Male'**
  String get male;

  /// No description provided for @female.
  ///
  /// In en, this message translates to:
  /// **'Female'**
  String get female;

  /// No description provided for @birthdate.
  ///
  /// In en, this message translates to:
  /// **'Birthdate'**
  String get birthdate;

  /// No description provided for @notSet.
  ///
  /// In en, this message translates to:
  /// **'Not set'**
  String get notSet;

  /// No description provided for @save.
  ///
  /// In en, this message translates to:
  /// **'Save'**
  String get save;

  /// No description provided for @members.
  ///
  /// In en, this message translates to:
  /// **'Members'**
  String get members;

  /// No description provided for @noMembersFound.
  ///
  /// In en, this message translates to:
  /// **'No members yet.'**
  String get noMembersFound;

  /// No description provided for @notifications.
  ///
  /// In en, this message translates to:
  /// **'Notifications'**
  String get notifications;

  /// No description provided for @noNotificationsFound.
  ///
  /// In en, this message translates to:
  /// **'No notifications yet.'**
  String get noNotificationsFound;

  /// No description provided for @announcements.
  ///
  /// In en, this message translates to:
  /// **'Announcements'**
  String get announcements;

  /// No description provided for @noPostsFound.
  ///
  /// In en, this message translates to:
  /// **'No posts yet.'**
  String get noPostsFound;

  /// No description provided for @holdRequests.
  ///
  /// In en, this message translates to:
  /// **'Hold Requests'**
  String get holdRequests;

  /// No description provided for @depositRequests.
  ///
  /// In en, this message translates to:
  /// **'Deposit Requests'**
  String get depositRequests;

  /// No description provided for @contractRequests.
  ///
  /// In en, this message translates to:
  /// **'Contract Requests'**
  String get contractRequests;

  /// No description provided for @noHoldRequestsFound.
  ///
  /// In en, this message translates to:
  /// **'No hold requests found.'**
  String get noHoldRequestsFound;

  /// No description provided for @noDepositRequestsFound.
  ///
  /// In en, this message translates to:
  /// **'No deposit requests found.'**
  String get noDepositRequestsFound;

  /// No description provided for @noContractRequestsFound.
  ///
  /// In en, this message translates to:
  /// **'No contract requests found.'**
  String get noContractRequestsFound;

  /// No description provided for @requestHold.
  ///
  /// In en, this message translates to:
  /// **'Request Hold'**
  String get requestHold;

  /// No description provided for @convertToDeposit.
  ///
  /// In en, this message translates to:
  /// **'Convert to Deposit'**
  String get convertToDeposit;

  /// No description provided for @requestContract.
  ///
  /// In en, this message translates to:
  /// **'Request Contract'**
  String get requestContract;

  /// No description provided for @changeUnitTerms.
  ///
  /// In en, this message translates to:
  /// **'Change unit / terms'**
  String get changeUnitTerms;

  /// No description provided for @approve.
  ///
  /// In en, this message translates to:
  /// **'Approve'**
  String get approve;

  /// No description provided for @reject.
  ///
  /// In en, this message translates to:
  /// **'Reject'**
  String get reject;

  /// No description provided for @cancel.
  ///
  /// In en, this message translates to:
  /// **'Cancel'**
  String get cancel;

  /// No description provided for @submitHoldRequest.
  ///
  /// In en, this message translates to:
  /// **'Submit Hold Request'**
  String get submitHoldRequest;

  /// No description provided for @submitDepositRequest.
  ///
  /// In en, this message translates to:
  /// **'Submit Deposit Request'**
  String get submitDepositRequest;

  /// No description provided for @submitContractRequest.
  ///
  /// In en, this message translates to:
  /// **'Submit Contract Request'**
  String get submitContractRequest;

  /// No description provided for @holdDuration.
  ///
  /// In en, this message translates to:
  /// **'Hold duration: {days} day{days, plural, one{} other{s}}'**
  String holdDuration(int days);

  /// No description provided for @unitStatus.
  ///
  /// In en, this message translates to:
  /// **'Status'**
  String get unitStatus;

  /// No description provided for @noActionsAvailable.
  ///
  /// In en, this message translates to:
  /// **'No actions available for this unit right now.'**
  String get noActionsAvailable;

  /// No description provided for @comments.
  ///
  /// In en, this message translates to:
  /// **'Comments'**
  String get comments;

  /// No description provided for @addComment.
  ///
  /// In en, this message translates to:
  /// **'Add a comment…'**
  String get addComment;

  /// No description provided for @noCommentsYet.
  ///
  /// In en, this message translates to:
  /// **'No comments yet.'**
  String get noCommentsYet;

  /// No description provided for @customer.
  ///
  /// In en, this message translates to:
  /// **'Customer'**
  String get customer;

  /// No description provided for @unit.
  ///
  /// In en, this message translates to:
  /// **'Unit'**
  String get unit;

  /// No description provided for @depositAndDiscount.
  ///
  /// In en, this message translates to:
  /// **'Deposit & Discount'**
  String get depositAndDiscount;

  /// No description provided for @paymentOption.
  ///
  /// In en, this message translates to:
  /// **'Payment Option'**
  String get paymentOption;

  /// No description provided for @remark.
  ///
  /// In en, this message translates to:
  /// **'Remark (optional)'**
  String get remark;

  /// No description provided for @viewContract.
  ///
  /// In en, this message translates to:
  /// **'View Contract'**
  String get viewContract;

  /// No description provided for @requestCancelReason.
  ///
  /// In en, this message translates to:
  /// **'Reason'**
  String get requestCancelReason;

  /// No description provided for @loanCalculator.
  ///
  /// In en, this message translates to:
  /// **'Loan Calculator'**
  String get loanCalculator;

  /// No description provided for @supportCenter.
  ///
  /// In en, this message translates to:
  /// **'Support Center'**
  String get supportCenter;

  /// No description provided for @aboutApp.
  ///
  /// In en, this message translates to:
  /// **'About BS Units'**
  String get aboutApp;

  /// No description provided for @logOut.
  ///
  /// In en, this message translates to:
  /// **'Log out'**
  String get logOut;

  /// No description provided for @search.
  ///
  /// In en, this message translates to:
  /// **'Search'**
  String get search;

  /// No description provided for @somethingWentWrong.
  ///
  /// In en, this message translates to:
  /// **'Something went wrong. Please try again.'**
  String get somethingWentWrong;

  /// No description provided for @cannotReachServer.
  ///
  /// In en, this message translates to:
  /// **'Cannot reach the server. Check your connection.'**
  String get cannotReachServer;

  /// No description provided for @updateRequired.
  ///
  /// In en, this message translates to:
  /// **'Update Required'**
  String get updateRequired;

  /// No description provided for @update.
  ///
  /// In en, this message translates to:
  /// **'Update'**
  String get update;

  /// No description provided for @retry.
  ///
  /// In en, this message translates to:
  /// **'Retry'**
  String get retry;

  /// No description provided for @unitsByStatus.
  ///
  /// In en, this message translates to:
  /// **'Units by status'**
  String get unitsByStatus;

  /// No description provided for @attachPhotos.
  ///
  /// In en, this message translates to:
  /// **'Attach photos'**
  String get attachPhotos;

  /// No description provided for @error.
  ///
  /// In en, this message translates to:
  /// **'Error'**
  String get error;

  /// No description provided for @failedToPostComment.
  ///
  /// In en, this message translates to:
  /// **'Failed to post comment.'**
  String get failedToPostComment;

  /// No description provided for @depositInProgressHint.
  ///
  /// In en, this message translates to:
  /// **'This unit has a deposit in progress. Once it\'s approved, request the contract from My Requests > Deposit Requests.'**
  String get depositInProgressHint;

  /// No description provided for @passwordChanged.
  ///
  /// In en, this message translates to:
  /// **'Password changed'**
  String get passwordChanged;

  /// No description provided for @passwordChangedMessage.
  ///
  /// In en, this message translates to:
  /// **'Your password has been updated.'**
  String get passwordChangedMessage;

  /// No description provided for @profileUpdated.
  ///
  /// In en, this message translates to:
  /// **'Profile updated'**
  String get profileUpdated;

  /// No description provided for @profileUpdatedMessage.
  ///
  /// In en, this message translates to:
  /// **'Your profile has been saved.'**
  String get profileUpdatedMessage;

  /// No description provided for @passwordReset.
  ///
  /// In en, this message translates to:
  /// **'Password reset'**
  String get passwordReset;

  /// No description provided for @passwordResetMessage.
  ///
  /// In en, this message translates to:
  /// **'You can now log in with your new password.'**
  String get passwordResetMessage;

  /// No description provided for @notification.
  ///
  /// In en, this message translates to:
  /// **'Notification'**
  String get notification;

  /// No description provided for @post.
  ///
  /// In en, this message translates to:
  /// **'Post'**
  String get post;

  /// No description provided for @failedToLoadPost.
  ///
  /// In en, this message translates to:
  /// **'Failed to load post.'**
  String get failedToLoadPost;

  /// No description provided for @reasonRequired.
  ///
  /// In en, this message translates to:
  /// **'Reason (required)'**
  String get reasonRequired;

  /// No description provided for @reasonOptional.
  ///
  /// In en, this message translates to:
  /// **'Reason (optional)'**
  String get reasonOptional;

  /// No description provided for @back.
  ///
  /// In en, this message translates to:
  /// **'Back'**
  String get back;

  /// No description provided for @confirm.
  ///
  /// In en, this message translates to:
  /// **'Confirm'**
  String get confirm;

  /// No description provided for @unknownStatus.
  ///
  /// In en, this message translates to:
  /// **'Unknown status'**
  String get unknownStatus;

  /// No description provided for @unitNumber.
  ///
  /// In en, this message translates to:
  /// **'Unit #{id}'**
  String unitNumber(String id);

  /// No description provided for @cancelHoldRequest.
  ///
  /// In en, this message translates to:
  /// **'Cancel hold request'**
  String get cancelHoldRequest;

  /// No description provided for @cancelDepositRequest.
  ///
  /// In en, this message translates to:
  /// **'Cancel deposit request'**
  String get cancelDepositRequest;

  /// No description provided for @cancelContractRequest.
  ///
  /// In en, this message translates to:
  /// **'Cancel contract request'**
  String get cancelContractRequest;

  /// No description provided for @rejectHoldRequest.
  ///
  /// In en, this message translates to:
  /// **'Reject hold request'**
  String get rejectHoldRequest;

  /// No description provided for @rejectDepositRequest.
  ///
  /// In en, this message translates to:
  /// **'Reject deposit request'**
  String get rejectDepositRequest;

  /// No description provided for @rejectContractRequest.
  ///
  /// In en, this message translates to:
  /// **'Reject contract request'**
  String get rejectContractRequest;

  /// No description provided for @contract.
  ///
  /// In en, this message translates to:
  /// **'Contract'**
  String get contract;

  /// No description provided for @holdRequested.
  ///
  /// In en, this message translates to:
  /// **'Hold requested'**
  String get holdRequested;

  /// No description provided for @holdRequestedMessage.
  ///
  /// In en, this message translates to:
  /// **'Your hold request for {unit} was submitted.'**
  String holdRequestedMessage(String unit);

  /// No description provided for @holdSubmitDescription.
  ///
  /// In en, this message translates to:
  /// **'Submitting this request will place a temporary hold on this unit pending approval.'**
  String get holdSubmitDescription;

  /// No description provided for @requestDeposit.
  ///
  /// In en, this message translates to:
  /// **'Request Deposit'**
  String get requestDeposit;

  /// No description provided for @listedPrice.
  ///
  /// In en, this message translates to:
  /// **'Listed price: {price}'**
  String listedPrice(String price);

  /// No description provided for @customerName.
  ///
  /// In en, this message translates to:
  /// **'Customer name'**
  String get customerName;

  /// No description provided for @customerPhone.
  ///
  /// In en, this message translates to:
  /// **'Customer phone'**
  String get customerPhone;

  /// No description provided for @addSecondCustomer.
  ///
  /// In en, this message translates to:
  /// **'Add a second customer (co-buyer)'**
  String get addSecondCustomer;

  /// No description provided for @secondCustomerName.
  ///
  /// In en, this message translates to:
  /// **'Second customer name'**
  String get secondCustomerName;

  /// No description provided for @secondCustomerPhone.
  ///
  /// In en, this message translates to:
  /// **'Second customer phone'**
  String get secondCustomerPhone;

  /// No description provided for @depositAmount.
  ///
  /// In en, this message translates to:
  /// **'Deposit amount'**
  String get depositAmount;

  /// No description provided for @otherDiscountAllowance.
  ///
  /// In en, this message translates to:
  /// **'Other discount allowance'**
  String get otherDiscountAllowance;

  /// No description provided for @useCustomLoanTerms.
  ///
  /// In en, this message translates to:
  /// **'Use custom loan terms instead of a preset option'**
  String get useCustomLoanTerms;

  /// No description provided for @loanDurationYears.
  ///
  /// In en, this message translates to:
  /// **'Loan duration (years)'**
  String get loanDurationYears;

  /// No description provided for @annualInterestRate.
  ///
  /// In en, this message translates to:
  /// **'Annual interest rate (%)'**
  String get annualInterestRate;

  /// No description provided for @specialDiscountPercent.
  ///
  /// In en, this message translates to:
  /// **'Special discount (%)'**
  String get specialDiscountPercent;

  /// No description provided for @hasFirstPayment.
  ///
  /// In en, this message translates to:
  /// **'Has a first payment'**
  String get hasFirstPayment;

  /// No description provided for @firstPaymentDurationMonths.
  ///
  /// In en, this message translates to:
  /// **'First payment duration (months)'**
  String get firstPaymentDurationMonths;

  /// No description provided for @firstPaymentPercentage.
  ///
  /// In en, this message translates to:
  /// **'First payment percentage (%)'**
  String get firstPaymentPercentage;

  /// No description provided for @depositRequested.
  ///
  /// In en, this message translates to:
  /// **'Deposit requested'**
  String get depositRequested;

  /// No description provided for @depositRequestedMessage.
  ///
  /// In en, this message translates to:
  /// **'Your deposit request for {unit} was submitted.'**
  String depositRequestedMessage(String unit);

  /// No description provided for @requestContractTitle.
  ///
  /// In en, this message translates to:
  /// **'Request Contract'**
  String get requestContractTitle;

  /// No description provided for @depositRequestLabel.
  ///
  /// In en, this message translates to:
  /// **'Deposit Request'**
  String get depositRequestLabel;

  /// No description provided for @depositRequestSummary.
  ///
  /// In en, this message translates to:
  /// **'#{id} · Unit #{unitId}'**
  String depositRequestSummary(String id, String unitId);

  /// No description provided for @signedDate.
  ///
  /// In en, this message translates to:
  /// **'Signed date'**
  String get signedDate;

  /// No description provided for @firstPaymentDate.
  ///
  /// In en, this message translates to:
  /// **'First payment date'**
  String get firstPaymentDate;

  /// No description provided for @attachSignedContractHint.
  ///
  /// In en, this message translates to:
  /// **'Attach at least one photo of the signed contract.'**
  String get attachSignedContractHint;

  /// No description provided for @attachmentRequiredError.
  ///
  /// In en, this message translates to:
  /// **'At least one attachment (e.g. signed contract page) is required.'**
  String get attachmentRequiredError;

  /// No description provided for @gallery.
  ///
  /// In en, this message translates to:
  /// **'Gallery'**
  String get gallery;

  /// No description provided for @camera.
  ///
  /// In en, this message translates to:
  /// **'Camera'**
  String get camera;

  /// No description provided for @contractRequested.
  ///
  /// In en, this message translates to:
  /// **'Contract requested'**
  String get contractRequested;

  /// No description provided for @contractRequestedMessage.
  ///
  /// In en, this message translates to:
  /// **'Your contract request was submitted.'**
  String get contractRequestedMessage;

  /// No description provided for @changeUnitTermsTitle.
  ///
  /// In en, this message translates to:
  /// **'Change Unit / Terms'**
  String get changeUnitTermsTitle;

  /// No description provided for @currentUnit.
  ///
  /// In en, this message translates to:
  /// **'Current unit'**
  String get currentUnit;

  /// No description provided for @searchDifferentUnitHint.
  ///
  /// In en, this message translates to:
  /// **'Search for a different unit (leave blank to keep current)'**
  String get searchDifferentUnitHint;

  /// No description provided for @newUnitLabel.
  ///
  /// In en, this message translates to:
  /// **'New unit: {name}'**
  String newUnitLabel(String name);

  /// No description provided for @updated.
  ///
  /// In en, this message translates to:
  /// **'Updated'**
  String get updated;

  /// No description provided for @depositRequestUpdatedMessage.
  ///
  /// In en, this message translates to:
  /// **'The deposit request was updated.'**
  String get depositRequestUpdatedMessage;

  /// No description provided for @saveChanges.
  ///
  /// In en, this message translates to:
  /// **'Save Changes'**
  String get saveChanges;

  /// No description provided for @paymentOptions.
  ///
  /// In en, this message translates to:
  /// **'Payment Options'**
  String get paymentOptions;

  /// No description provided for @noPaymentOptionsForUnitType.
  ///
  /// In en, this message translates to:
  /// **'No payment options for this unit type.'**
  String get noPaymentOptionsForUnitType;

  /// No description provided for @depositAmountShort.
  ///
  /// In en, this message translates to:
  /// **'Deposit: {amount}'**
  String depositAmountShort(String amount);

  /// No description provided for @loanDurationYearsShort.
  ///
  /// In en, this message translates to:
  /// **'{years} yrs'**
  String loanDurationYearsShort(String years);

  /// No description provided for @interestPercentShort.
  ///
  /// In en, this message translates to:
  /// **'{percent}% interest'**
  String interestPercentShort(String percent);

  /// No description provided for @unitsByStatusTitle.
  ///
  /// In en, this message translates to:
  /// **'{project} — Units by Status'**
  String unitsByStatusTitle(String project);

  /// No description provided for @failedToLoadUnits.
  ///
  /// In en, this message translates to:
  /// **'Failed to load units.'**
  String get failedToLoadUnits;

  /// No description provided for @searchUnitCodeHint.
  ///
  /// In en, this message translates to:
  /// **'Search unit code…'**
  String get searchUnitCodeHint;

  /// No description provided for @startTypingUnitCode.
  ///
  /// In en, this message translates to:
  /// **'Start typing a unit code.'**
  String get startTypingUnitCode;

  /// No description provided for @loanAmount.
  ///
  /// In en, this message translates to:
  /// **'Loan amount'**
  String get loanAmount;

  /// No description provided for @numberOfMonths.
  ///
  /// In en, this message translates to:
  /// **'Number of months'**
  String get numberOfMonths;

  /// No description provided for @calculate.
  ///
  /// In en, this message translates to:
  /// **'Calculate'**
  String get calculate;

  /// No description provided for @monthlyPayment.
  ///
  /// In en, this message translates to:
  /// **'Monthly payment'**
  String get monthlyPayment;

  /// No description provided for @totalPayment.
  ///
  /// In en, this message translates to:
  /// **'Total payment'**
  String get totalPayment;

  /// No description provided for @totalInterest.
  ///
  /// In en, this message translates to:
  /// **'Total interest'**
  String get totalInterest;

  /// No description provided for @telegramGroup.
  ///
  /// In en, this message translates to:
  /// **'Telegram Group'**
  String get telegramGroup;

  /// No description provided for @versionLabel.
  ///
  /// In en, this message translates to:
  /// **'Version {version} ({build})'**
  String versionLabel(String version, String build);

  /// No description provided for @failedToLoadContractPdf.
  ///
  /// In en, this message translates to:
  /// **'Failed to load the contract PDF.'**
  String get failedToLoadContractPdf;

  /// No description provided for @updateRequiredMessage.
  ///
  /// In en, this message translates to:
  /// **'A new version ({version}) is required to continue using this app.'**
  String updateRequiredMessage(String version);

  /// No description provided for @documentScanner.
  ///
  /// In en, this message translates to:
  /// **'Document Scanner'**
  String get documentScanner;

  /// No description provided for @documentScannerHint.
  ///
  /// In en, this message translates to:
  /// **'Take a photo of a document or ID card, crop it, and save it to your gallery.'**
  String get documentScannerHint;

  /// No description provided for @takePhoto.
  ///
  /// In en, this message translates to:
  /// **'Take Photo'**
  String get takePhoto;

  /// No description provided for @scanAnother.
  ///
  /// In en, this message translates to:
  /// **'Scan Another'**
  String get scanAnother;

  /// No description provided for @done.
  ///
  /// In en, this message translates to:
  /// **'Done'**
  String get done;

  /// No description provided for @imageSaved.
  ///
  /// In en, this message translates to:
  /// **'Image saved'**
  String get imageSaved;

  /// No description provided for @imageSavedToGallery.
  ///
  /// In en, this message translates to:
  /// **'The image was saved to your gallery.'**
  String get imageSavedToGallery;

  /// No description provided for @failedToSaveImage.
  ///
  /// In en, this message translates to:
  /// **'Failed to save the image.'**
  String get failedToSaveImage;

  /// No description provided for @cropImage.
  ///
  /// In en, this message translates to:
  /// **'Crop Image'**
  String get cropImage;

  /// No description provided for @showPassword.
  ///
  /// In en, this message translates to:
  /// **'Show password'**
  String get showPassword;

  /// No description provided for @hidePassword.
  ///
  /// In en, this message translates to:
  /// **'Hide password'**
  String get hidePassword;

  /// No description provided for @unitCode.
  ///
  /// In en, this message translates to:
  /// **'Unit Code'**
  String get unitCode;

  /// No description provided for @project.
  ///
  /// In en, this message translates to:
  /// **'Project'**
  String get project;

  /// No description provided for @unitType.
  ///
  /// In en, this message translates to:
  /// **'Unit Type'**
  String get unitType;

  /// No description provided for @unitPriceLabel.
  ///
  /// In en, this message translates to:
  /// **'Unit Price (\$)'**
  String get unitPriceLabel;

  /// No description provided for @houseSizeLabel.
  ///
  /// In en, this message translates to:
  /// **'House size'**
  String get houseSizeLabel;

  /// No description provided for @landSizeLabel.
  ///
  /// In en, this message translates to:
  /// **'Land size'**
  String get landSizeLabel;

  /// No description provided for @bedroomsLabel.
  ///
  /// In en, this message translates to:
  /// **'Bedrooms'**
  String get bedroomsLabel;

  /// No description provided for @bathroomsLabel.
  ///
  /// In en, this message translates to:
  /// **'Bathrooms'**
  String get bathroomsLabel;

  /// No description provided for @livingRoomsLabel.
  ///
  /// In en, this message translates to:
  /// **'Living rooms'**
  String get livingRoomsLabel;

  /// No description provided for @kitchensLabel.
  ///
  /// In en, this message translates to:
  /// **'Kitchens'**
  String get kitchensLabel;

  /// No description provided for @swimmingPoolsLabel.
  ///
  /// In en, this message translates to:
  /// **'Swimming pools'**
  String get swimmingPoolsLabel;

  /// No description provided for @unitInformationTitle.
  ///
  /// In en, this message translates to:
  /// **'Unit\'s Information'**
  String get unitInformationTitle;

  /// No description provided for @contractPriceNote.
  ///
  /// In en, this message translates to:
  /// **'This is the contractual price before other discounts.'**
  String get contractPriceNote;

  /// No description provided for @holdTabLabel.
  ///
  /// In en, this message translates to:
  /// **'Hold Req'**
  String get holdTabLabel;

  /// No description provided for @depositTabLabel.
  ///
  /// In en, this message translates to:
  /// **'Deposit Req'**
  String get depositTabLabel;

  /// No description provided for @contractTabLabel.
  ///
  /// In en, this message translates to:
  /// **'Contract Req'**
  String get contractTabLabel;

  /// No description provided for @filterTitle.
  ///
  /// In en, this message translates to:
  /// **'Filters'**
  String get filterTitle;

  /// No description provided for @allProjectsLabel.
  ///
  /// In en, this message translates to:
  /// **'All projects'**
  String get allProjectsLabel;

  /// No description provided for @availableAndUnavailable.
  ///
  /// In en, this message translates to:
  /// **'Available & unavailable'**
  String get availableAndUnavailable;

  /// No description provided for @allStatusesLabel.
  ///
  /// In en, this message translates to:
  /// **'All statuses'**
  String get allStatusesLabel;

  /// No description provided for @applyFiltersLabel.
  ///
  /// In en, this message translates to:
  /// **'Apply filters'**
  String get applyFiltersLabel;

  /// No description provided for @detailLoadError.
  ///
  /// In en, this message translates to:
  /// **'Could not load the latest unit details. Please retry.'**
  String get detailLoadError;

  /// No description provided for @previewLabel.
  ///
  /// In en, this message translates to:
  /// **'Preview'**
  String get previewLabel;

  /// No description provided for @exteriorLabel.
  ///
  /// In en, this message translates to:
  /// **'Exterior'**
  String get exteriorLabel;

  /// No description provided for @interiorLabel.
  ///
  /// In en, this message translates to:
  /// **'Interior'**
  String get interiorLabel;

  /// No description provided for @floorPlanLabel.
  ///
  /// In en, this message translates to:
  /// **'Floor Plan'**
  String get floorPlanLabel;

  /// No description provided for @noImagesLabel.
  ///
  /// In en, this message translates to:
  /// **'No images available'**
  String get noImagesLabel;

  /// No description provided for @paymentOptionsLoadError.
  ///
  /// In en, this message translates to:
  /// **'Payment options could not be loaded. Retry or choose Other.'**
  String get paymentOptionsLoadError;

  /// No description provided for @requiredFieldLabel.
  ///
  /// In en, this message translates to:
  /// **'This field is required.'**
  String get requiredFieldLabel;

  /// No description provided for @invalidPhoneLabel.
  ///
  /// In en, this message translates to:
  /// **'Enter a phone number starting with 0 (9–10 digits).'**
  String get invalidPhoneLabel;

  /// No description provided for @invalidNumberLabel.
  ///
  /// In en, this message translates to:
  /// **'Enter a valid number in the allowed range.'**
  String get invalidNumberLabel;

  /// No description provided for @customerInformationTitle.
  ///
  /// In en, this message translates to:
  /// **'Customer\'s Information'**
  String get customerInformationTitle;

  /// No description provided for @customerOneNameLabel.
  ///
  /// In en, this message translates to:
  /// **'Surname and Name (Customer 1) *'**
  String get customerOneNameLabel;

  /// No description provided for @customerTwoNameLabel.
  ///
  /// In en, this message translates to:
  /// **'Surname and Name (Customer 2)'**
  String get customerTwoNameLabel;

  /// No description provided for @customerOneGenderLabel.
  ///
  /// In en, this message translates to:
  /// **'Gender (Customer 1) *'**
  String get customerOneGenderLabel;

  /// No description provided for @customerTwoGenderLabel.
  ///
  /// In en, this message translates to:
  /// **'Gender (Customer 2)'**
  String get customerTwoGenderLabel;

  /// No description provided for @customerPhonesTitle.
  ///
  /// In en, this message translates to:
  /// **'Phone Numbers (Customer)'**
  String get customerPhonesTitle;

  /// No description provided for @phoneLineOneLabel.
  ///
  /// In en, this message translates to:
  /// **'Line 1 *'**
  String get phoneLineOneLabel;

  /// No description provided for @phoneLineTwoLabel.
  ///
  /// In en, this message translates to:
  /// **'Line 2'**
  String get phoneLineTwoLabel;

  /// No description provided for @otherDiscountDepositTitle.
  ///
  /// In en, this message translates to:
  /// **'Other Discount & Deposit'**
  String get otherDiscountDepositTitle;

  /// No description provided for @otherDiscountNote.
  ///
  /// In en, this message translates to:
  /// **'A company-approved discount stated in the contract, not an agent discount. Requires sales manager approval.'**
  String get otherDiscountNote;

  /// No description provided for @promotionalDiscountLabel.
  ///
  /// In en, this message translates to:
  /// **'Promotional Discount (\$)'**
  String get promotionalDiscountLabel;

  /// No description provided for @promotionalDiscountNote.
  ///
  /// In en, this message translates to:
  /// **'Calculated automatically from the active promotion by the server.'**
  String get promotionalDiscountNote;

  /// No description provided for @noneLabel.
  ///
  /// In en, this message translates to:
  /// **'None'**
  String get noneLabel;

  /// No description provided for @otherLabel.
  ///
  /// In en, this message translates to:
  /// **'Other'**
  String get otherLabel;

  /// No description provided for @standardLabel.
  ///
  /// In en, this message translates to:
  /// **'Standard'**
  String get standardLabel;

  /// No description provided for @otherPaymentOptionNote.
  ///
  /// In en, this message translates to:
  /// **'Other payment options require sales manager approval.'**
  String get otherPaymentOptionNote;

  /// No description provided for @installmentMonthsLabel.
  ///
  /// In en, this message translates to:
  /// **'Installment Duration (months)'**
  String get installmentMonthsLabel;

  /// No description provided for @downPaymentLabel.
  ///
  /// In en, this message translates to:
  /// **'Down Payment'**
  String get downPaymentLabel;

  /// No description provided for @downPaymentAmountLabel.
  ///
  /// In en, this message translates to:
  /// **'Down Payment Amount (\$)'**
  String get downPaymentAmountLabel;

  /// No description provided for @remarkPlaceholder.
  ///
  /// In en, this message translates to:
  /// **'Enter your remark…'**
  String get remarkPlaceholder;

  /// No description provided for @saveLabel.
  ///
  /// In en, this message translates to:
  /// **'Save'**
  String get saveLabel;

  /// No description provided for @nextLabel.
  ///
  /// In en, this message translates to:
  /// **'Next'**
  String get nextLabel;

  /// No description provided for @yes.
  ///
  /// In en, this message translates to:
  /// **'Yes'**
  String get yes;

  /// No description provided for @no.
  ///
  /// In en, this message translates to:
  /// **'No'**
  String get no;

  /// No description provided for @holdButtonLabel.
  ///
  /// In en, this message translates to:
  /// **'Hold'**
  String get holdButtonLabel;

  /// No description provided for @depositButtonLabel.
  ///
  /// In en, this message translates to:
  /// **'Deposit'**
  String get depositButtonLabel;

  /// No description provided for @customPaymentHint.
  ///
  /// In en, this message translates to:
  /// **'Choose Other to enter custom payment terms.'**
  String get customPaymentHint;

  /// No description provided for @holdDaysLabel.
  ///
  /// In en, this message translates to:
  /// **'Days'**
  String get holdDaysLabel;

  /// No description provided for @expirationDateLabel.
  ///
  /// In en, this message translates to:
  /// **'Expiration Date'**
  String get expirationDateLabel;

  /// No description provided for @holdDateLabel.
  ///
  /// In en, this message translates to:
  /// **'Hold Date'**
  String get holdDateLabel;

  /// No description provided for @requestDateLabel.
  ///
  /// In en, this message translates to:
  /// **'Request Date'**
  String get requestDateLabel;

  /// No description provided for @requestedByLabel.
  ///
  /// In en, this message translates to:
  /// **'Requested by'**
  String get requestedByLabel;

  /// No description provided for @receivedAmountLabel.
  ///
  /// In en, this message translates to:
  /// **'Received Amount'**
  String get receivedAmountLabel;

  /// No description provided for @salesManagerLabel.
  ///
  /// In en, this message translates to:
  /// **'Sales Manager'**
  String get salesManagerLabel;

  /// No description provided for @unitControllerLabel.
  ///
  /// In en, this message translates to:
  /// **'Unit Controller'**
  String get unitControllerLabel;

  /// No description provided for @floorLabel.
  ///
  /// In en, this message translates to:
  /// **'Floor'**
  String get floorLabel;

  /// No description provided for @holdRequestTitle.
  ///
  /// In en, this message translates to:
  /// **'Hold Request'**
  String get holdRequestTitle;

  /// No description provided for @depositRequestTitle.
  ///
  /// In en, this message translates to:
  /// **'Deposit Request'**
  String get depositRequestTitle;

  /// No description provided for @requestAttachmentsTitle.
  ///
  /// In en, this message translates to:
  /// **'Attachments *'**
  String get requestAttachmentsTitle;

  /// No description provided for @requestAttachmentsNote.
  ///
  /// In en, this message translates to:
  /// **'Include: ID card, passport, or other related documents.'**
  String get requestAttachmentsNote;
}

class _AppLocalizationsDelegate
    extends LocalizationsDelegate<AppLocalizations> {
  const _AppLocalizationsDelegate();

  @override
  Future<AppLocalizations> load(Locale locale) {
    return SynchronousFuture<AppLocalizations>(lookupAppLocalizations(locale));
  }

  @override
  bool isSupported(Locale locale) =>
      <String>['en', 'km'].contains(locale.languageCode);

  @override
  bool shouldReload(_AppLocalizationsDelegate old) => false;
}

AppLocalizations lookupAppLocalizations(Locale locale) {
  // Lookup logic when only language code is specified.
  switch (locale.languageCode) {
    case 'en':
      return AppLocalizationsEn();
    case 'km':
      return AppLocalizationsKm();
  }

  throw FlutterError(
    'AppLocalizations.delegate failed to load unsupported locale "$locale". This is likely '
    'an issue with the localizations generation tool. Please file an issue '
    'on GitHub with a reproducible sample app and the gen-l10n configuration '
    'that was used.',
  );
}
