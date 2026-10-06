// ignore: unused_import
import 'package:intl/intl.dart' as intl;

import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for Khmer Central Khmer (`km`).
class AppLocalizationsKm extends AppLocalizations {
  AppLocalizationsKm([String locale = 'km']) : super(locale);

  @override
  String get appTitle => 'BS Units';

  @override
  String get home => 'ទំព័រដើម';

  @override
  String get more => 'ផ្សេងៗ';

  @override
  String get projects => 'គម្រោង';

  @override
  String get units => 'អចលនទ្រព្យ';

  @override
  String get unitTypes => 'ប្រភេទអចលនទ្រព្យ';

  @override
  String get searchByUnitCode => 'ស្វែងរកតាមលេខអចលនទ្រព្យ…';

  @override
  String get loadMore => 'មើលបន្ថែម';

  @override
  String get noProjectsFound => 'មិនទាន់មានគម្រោងទេ។';

  @override
  String get noUnitsFound => 'រកមិនឃើញអចលនទ្រព្យទេ។';

  @override
  String get phoneNumber => 'លេខទូរស័ព្ទ';

  @override
  String get password => 'ពាក្យសម្ងាត់';

  @override
  String get logIn => 'ចូល';

  @override
  String get forgotPassword => 'ភ្លេចពាក្យសម្ងាត់?';

  @override
  String get pleaseEnterPhoneAndPassword =>
      'សូមបញ្ចូលលេខទូរស័ព្ទ និងពាក្យសម្ងាត់របស់អ្នក។';

  @override
  String get resetPassword => 'កំណត់ពាក្យសម្ងាត់ឡើងវិញ';

  @override
  String get enterPhoneForCode =>
      'សូមបញ្ចូលលេខទូរស័ព្ទរបស់អ្នកដើម្បីទទួលកូដផ្ទៀងផ្ទាត់តាមសារ SMS។';

  @override
  String get sendCode => 'ផ្ញើកូដ';

  @override
  String enterCodeSentTo(String phone) {
    return 'សូមបញ្ចូលកូដដែលបានផ្ញើទៅ $phone។';
  }

  @override
  String get verificationCode => 'កូដផ្ទៀងផ្ទាត់';

  @override
  String get verify => 'ផ្ទៀងផ្ទាត់';

  @override
  String get enterNewPassword => 'សូមបញ្ចូលពាក្យសម្ងាត់ថ្មីរបស់អ្នក។';

  @override
  String get newPassword => 'ពាក្យសម្ងាត់​ថ្មី';

  @override
  String get changePassword => 'ផ្លាស់ប្តូរពាក្យសម្ងាត់';

  @override
  String get currentPassword => 'ពាក្យសម្ងាត់​បច្ចុប្បន្ន';

  @override
  String get newPasswordMinChars => 'ពាក្យសម្ងាត់​ថ្មី (យ៉ាងតិច ៦ តួអក្សរ)';

  @override
  String get profile => 'ប្រូហ្វាល';

  @override
  String get name => 'ឈ្មោះ';

  @override
  String get email => 'អុីម៉ែល';

  @override
  String get gender => 'ភេទ';

  @override
  String get male => 'ប្រុស';

  @override
  String get female => 'ស្រី';

  @override
  String get birthdate => 'ថ្ងៃខែឆ្នាំកំណើត';

  @override
  String get notSet => 'មិនទាន់កំណត់';

  @override
  String get save => 'រក្សាទុក';

  @override
  String get members => 'សមាជិកក្រុម';

  @override
  String get noMembersFound => 'មិនទាន់មានសមាជិកទេ។';

  @override
  String get notifications => 'សេចក្ដីជូនដំណឹង';

  @override
  String get noNotificationsFound => 'មិនទាន់មានសេចក្ដីជូនដំណឹងទេ។';

  @override
  String get announcements => 'សេចក្ដីប្រកាស';

  @override
  String get noPostsFound => 'មិនទាន់មានសេចក្ដីប្រកាសទេ។';

  @override
  String get holdRequests => 'សំម្នើរព្យួរ';

  @override
  String get depositRequests => 'សំម្នើរកក់ប្រាក់';

  @override
  String get contractRequests => 'សំម្នើរកុងត្រា';

  @override
  String get noHoldRequestsFound => 'រកមិនឃើញសំម្នើរព្យួរទេ។';

  @override
  String get noDepositRequestsFound => 'រកមិនឃើញសំម្នើរកក់ប្រាក់ទេ។';

  @override
  String get noContractRequestsFound => 'រកមិនឃើញសំម្នើរកុងត្រាទេ។';

  @override
  String get requestHold => 'ស្នើរព្យួរ';

  @override
  String get convertToDeposit => 'ប្តូរទៅជាកក់ប្រាក់';

  @override
  String get requestContract => 'ស្នើរកុងត្រា';

  @override
  String get changeUnitTerms => 'ផ្លាស់ប្តូរអចលនទ្រព្យ';

  @override
  String get approve => 'អនុម័ត';

  @override
  String get reject => 'បដិសេធ';

  @override
  String get cancel => 'បោះបង់';

  @override
  String get submitHoldRequest => 'ដាក់ស្នើសំម្នើរព្យួរ';

  @override
  String get submitDepositRequest => 'ដាក់ស្នើសំម្នើរកក់ប្រាក់';

  @override
  String get submitContractRequest => 'ដាក់ស្នើសំម្នើរកុងត្រា';

  @override
  String holdDuration(int days) {
    return 'រយៈពេលព្យួរ៖ $days ថ្ងៃ';
  }

  @override
  String get unitStatus => 'ស្ថានភាព';

  @override
  String get noActionsAvailable =>
      'គ្មានសកម្មភាពសម្រាប់អចលនទ្រព្យនេះទេនៅពេលនេះ។';

  @override
  String get comments => 'មតិយោបល់';

  @override
  String get addComment => 'បន្ថែមមតិយោបល់…';

  @override
  String get noCommentsYet => 'មិនទាន់មានមតិយោបល់ទេ។';

  @override
  String get customer => 'អតិថិជន';

  @override
  String get unit => 'អចលនទ្រព្យ';

  @override
  String get depositAndDiscount => 'ប្រាក់កក់ និង ការបញ្ចុះតម្លៃ';

  @override
  String get paymentOption => 'ជម្រើសនៃការបង់ប្រាក់';

  @override
  String get remark => 'សម្គាល់ (ស្រេចចិត្ត)';

  @override
  String get viewContract => 'ពិនិត្យកុងត្រា';

  @override
  String get requestCancelReason => 'មូលហេតុ';

  @override
  String get loanCalculator => 'គណនាកម្ចី';

  @override
  String get supportCenter => 'មជ្ឈមណ្ឌលជំនួយ';

  @override
  String get aboutApp => 'អំពី BS Units';

  @override
  String get logOut => 'ចាកចេញ';

  @override
  String get search => 'ស្វែងរក';

  @override
  String get somethingWentWrong => 'មានបញ្ហាកើតឡើង។ សូមព្យាយាមម្តងទៀត។';

  @override
  String get cannotReachServer =>
      'មិនអាចភ្ជាប់ទៅម៉ាស៊ីនមេបានទេ។ សូមពិនិត្យការតភ្ជាប់អ៊ីនធឺណិតរបស់អ្នក។';

  @override
  String get updateRequired => 'តម្រូវឱ្យធ្វើបច្ចុប្បន្នភាព';

  @override
  String get update => 'ធ្វើបច្ចុប្បន្នភាព';

  @override
  String get retry => 'ព្យាយាមម្តងទៀត';

  @override
  String get unitsByStatus => 'អចលនទ្រព្យតាមស្ថានភាព';

  @override
  String get attachPhotos => 'ភ្ជាប់រូបភាព';

  @override
  String get error => 'កំហុស';

  @override
  String get failedToPostComment => 'បរាជ័យក្នុងការផ្ញើមតិយោបល់។';

  @override
  String get depositInProgressHint =>
      'អចលនទ្រព្យនេះកំពុងស្ថិតក្នុងដំណើរការកក់ប្រាក់។ នៅពេលវាត្រូវបានអនុម័ត សូមស្នើសុំកុងត្រាតាមរយៈ សំណើររបស់ខ្ញុំ > សំម្នើរកក់ប្រាក់។';

  @override
  String get passwordChanged => 'ពាក្យសម្ងាត់ត្រូវបានផ្លាស់ប្តូរ';

  @override
  String get passwordChangedMessage =>
      'ពាក្យសម្ងាត់របស់អ្នកត្រូវបានធ្វើបច្ចុប្បន្នភាព។';

  @override
  String get profileUpdated => 'ប្រូហ្វាលត្រូវបានធ្វើបច្ចុប្បន្នភាព';

  @override
  String get profileUpdatedMessage => 'ប្រូហ្វាលរបស់អ្នកត្រូវបានរក្សាទុក។';

  @override
  String get passwordReset => 'ពាក្យសម្ងាត់ត្រូវបានកំណត់ឡើងវិញ';

  @override
  String get passwordResetMessage =>
      'ឥឡូវអ្នកអាចចូលដោយប្រើពាក្យសម្ងាត់ថ្មីរបស់អ្នក។';

  @override
  String get notification => 'សេចក្ដីជូនដំណឹង';

  @override
  String get post => 'ប្រកាស';

  @override
  String get failedToLoadPost => 'បរាជ័យក្នុងការផ្ទុកប្រកាស។';

  @override
  String get reasonRequired => 'មូលហេតុ (ត្រូវការ)';

  @override
  String get reasonOptional => 'មូលហេតុ (ស្រេចចិត្ត)';

  @override
  String get back => 'ត្រឡប់ក្រោយ';

  @override
  String get confirm => 'បញ្ជាក់';

  @override
  String get unknownStatus => 'ស្ថានភាពមិនស្គាល់';

  @override
  String unitNumber(String id) {
    return 'អចលនទ្រព្យ #$id';
  }

  @override
  String get cancelHoldRequest => 'បោះបង់សំម្នើរព្យួរ';

  @override
  String get cancelDepositRequest => 'បោះបង់សំម្នើរកក់ប្រាក់';

  @override
  String get cancelContractRequest => 'បោះបង់សំម្នើរកុងត្រា';

  @override
  String get rejectHoldRequest => 'បដិសេធសំម្នើរព្យួរ';

  @override
  String get rejectDepositRequest => 'បដិសេធសំម្នើរកក់ប្រាក់';

  @override
  String get rejectContractRequest => 'បដិសេធសំម្នើរកុងត្រា';

  @override
  String get contract => 'កុងត្រា';

  @override
  String get holdRequested => 'បានស្នើសុំព្យួរ';

  @override
  String holdRequestedMessage(String unit) {
    return 'សំម្នើរព្យួររបស់អ្នកសម្រាប់ $unit ត្រូវបានដាក់ស្នើ។';
  }

  @override
  String get holdSubmitDescription =>
      'ការដាក់ស្នើសំម្នើរនេះនឹងធ្វើការព្យួរអចលនទ្រព្យនេះជាបណ្តោះអាសន្នរហូតដល់មានការអនុម័ត។';

  @override
  String get requestDeposit => 'ស្នើសុំកក់ប្រាក់';

  @override
  String listedPrice(String price) {
    return 'តម្លៃដាក់លក់៖ $price';
  }

  @override
  String get customerName => 'ឈ្មោះអតិថិជន';

  @override
  String get customerPhone => 'លេខទូរស័ព្ទអតិថិជន';

  @override
  String get addSecondCustomer => 'បន្ថែមអតិថិជនទីពីរ (អ្នកទិញរួម)';

  @override
  String get secondCustomerName => 'ឈ្មោះអតិថិជនទីពីរ';

  @override
  String get secondCustomerPhone => 'លេខទូរស័ព្ទអតិថិជនទីពីរ';

  @override
  String get depositAmount => 'ចំនួនប្រាក់កក់';

  @override
  String get otherDiscountAllowance => 'ការបញ្ចុះតម្លៃផ្សេងទៀត';

  @override
  String get useCustomLoanTerms =>
      'ប្រើលក្ខខណ្ឌកម្ចីផ្ទាល់ខ្លួន ជំនួសឱ្យជម្រើសដែលមានស្រាប់';

  @override
  String get loanDurationYears => 'រយៈពេលកម្ចី (ឆ្នាំ)';

  @override
  String get annualInterestRate => 'អត្រាការប្រាក់ប្រចាំឆ្នាំ (%)';

  @override
  String get specialDiscountPercent => 'ការបញ្ចុះតម្លៃពិសេស (%)';

  @override
  String get hasFirstPayment => 'មានការបង់ប្រាក់លើកដំបូង';

  @override
  String get firstPaymentDurationMonths => 'រយៈពេលបង់ប្រាក់លើកដំបូង (ខែ)';

  @override
  String get firstPaymentPercentage => 'ភាគរយបង់ប្រាក់លើកដំបូង (%)';

  @override
  String get depositRequested => 'បានស្នើសុំកក់ប្រាក់';

  @override
  String depositRequestedMessage(String unit) {
    return 'សំម្នើរកក់ប្រាក់របស់អ្នកសម្រាប់ $unit ត្រូវបានដាក់ស្នើ។';
  }

  @override
  String get requestContractTitle => 'ស្នើសុំកុងត្រា';

  @override
  String get depositRequestLabel => 'សំម្នើរកក់ប្រាក់';

  @override
  String depositRequestSummary(String id, String unitId) {
    return '#$id · អចលនទ្រព្យ #$unitId';
  }

  @override
  String get signedDate => 'កាលបរិច្ឆេទចុះហត្ថលេខា';

  @override
  String get firstPaymentDate => 'កាលបរិច្ឆេទបង់ប្រាក់លើកដំបូង';

  @override
  String get attachSignedContractHint =>
      'សូមភ្ជាប់រូបភាពយ៉ាងតិចមួយសន្លឹកនៃកុងត្រាដែលបានចុះហត្ថលេខា។';

  @override
  String get attachmentRequiredError =>
      'ត្រូវការឯកសារភ្ជាប់យ៉ាងតិចមួយ (ឧទាហរណ៍ ទំព័រកុងត្រាដែលបានចុះហត្ថលេខា)។';

  @override
  String get gallery => 'វិចិត្រសាល';

  @override
  String get camera => 'កាមេរ៉ា';

  @override
  String get contractRequested => 'បានស្នើសុំកុងត្រា';

  @override
  String get contractRequestedMessage =>
      'សំម្នើរកុងត្រារបស់អ្នកត្រូវបានដាក់ស្នើ។';

  @override
  String get changeUnitTermsTitle => 'ផ្លាស់ប្តូរអចលនទ្រព្យ / លក្ខខណ្ឌ';

  @override
  String get currentUnit => 'អចលនទ្រព្យបច្ចុប្បន្ន';

  @override
  String get searchDifferentUnitHint =>
      'ស្វែងរកអចលនទ្រព្យផ្សេង (ទុកទទេដើម្បីរក្សាទុកបច្ចុប្បន្ន)';

  @override
  String newUnitLabel(String name) {
    return 'អចលនទ្រព្យថ្មី៖ $name';
  }

  @override
  String get updated => 'បានធ្វើបច្ចុប្បន្នភាព';

  @override
  String get depositRequestUpdatedMessage =>
      'សំម្នើរកក់ប្រាក់ត្រូវបានធ្វើបច្ចុប្បន្នភាព។';

  @override
  String get saveChanges => 'រក្សាទុកការផ្លាស់ប្តូរ';

  @override
  String get paymentOptions => 'ជម្រើសនៃការបង់ប្រាក់';

  @override
  String get noPaymentOptionsForUnitType =>
      'គ្មានជម្រើសនៃការបង់ប្រាក់សម្រាប់ប្រភេទអចលនទ្រព្យនេះទេ។';

  @override
  String depositAmountShort(String amount) {
    return 'ប្រាក់កក់៖ $amount';
  }

  @override
  String loanDurationYearsShort(String years) {
    return '$years ឆ្នាំ';
  }

  @override
  String interestPercentShort(String percent) {
    return 'ការប្រាក់ $percent%';
  }

  @override
  String unitsByStatusTitle(String project) {
    return '$project — អចលនទ្រព្យតាមស្ថានភាព';
  }

  @override
  String get failedToLoadUnits => 'បរាជ័យក្នុងការផ្ទុកអចលនទ្រព្យ។';

  @override
  String get searchUnitCodeHint => 'ស្វែងរកលេខអចលនទ្រព្យ…';

  @override
  String get startTypingUnitCode => 'សូមចាប់ផ្តើមវាយលេខអចលនទ្រព្យ។';

  @override
  String get loanAmount => 'ចំនួនប្រាក់កម្ចី';

  @override
  String get numberOfMonths => 'ចំនួនខែ';

  @override
  String get calculate => 'គណនា';

  @override
  String get monthlyPayment => 'ការបង់ប្រាក់ប្រចាំខែ';

  @override
  String get totalPayment => 'ការបង់ប្រាក់សរុប';

  @override
  String get totalInterest => 'ការប្រាក់សរុប';

  @override
  String get telegramGroup => 'ក្រុម Telegram';

  @override
  String versionLabel(String version, String build) {
    return 'កំណែ $version ($build)';
  }

  @override
  String get failedToLoadContractPdf => 'បរាជ័យក្នុងការផ្ទុកឯកសារកុងត្រា PDF។';

  @override
  String updateRequiredMessage(String version) {
    return 'តម្រូវឱ្យមានកំណែថ្មី ($version) ដើម្បីបន្តប្រើកម្មវិធីនេះ។';
  }

  @override
  String get documentScanner => 'ស្កេនឯកសារ';

  @override
  String get documentScannerHint =>
      'ថតរូបឯកសារ ឬអត្តសញ្ញាណប័ណ្ណ កាត់រូបភាព រួចរក្សាទុកទៅកាន់វិចិត្រសាលរបស់អ្នក។';

  @override
  String get takePhoto => 'ថតរូប';

  @override
  String get scanAnother => 'ស្កេនម្តងទៀត';

  @override
  String get done => 'រួចរាល់';

  @override
  String get imageSaved => 'រូបភាពត្រូវបានរក្សាទុក';

  @override
  String get imageSavedToGallery =>
      'រូបភាពត្រូវបានរក្សាទុកទៅកាន់វិចិត្រសាលរបស់អ្នក។';

  @override
  String get failedToSaveImage => 'បរាជ័យក្នុងការរក្សាទុករូបភាព។';

  @override
  String get cropImage => 'កាត់រូបភាព';

  @override
  String get showPassword => 'បង្ហាញពាក្យសម្ងាត់';

  @override
  String get hidePassword => 'លាក់ពាក្យសម្ងាត់';
}
