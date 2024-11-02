=== bizExaminer LearnDash Extension ===
Contributors: bizexaminer, gaambo
Tags: LearnDash, LMS, Exams
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.5.2
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html

bizExaminer is a solution to create, conduct and manage online exams.
Our LearnDash extension allows you to connect quizzes with bizExaminer exams.

== Description ==
**Important: This plugin requires PHP 7.4 or higher and LearnDash 4.3 or higher.**
Tested up to Learndash 4.18.0.1 (without new [experimental in-progress features and experiments of LearnDash](https://developers.learndash.com/constant/learndash_enable_in_progress_features/)).

bizExaminer is a complete and stable solution for stress-free examination.

= Features of bizExaminer =

- Integration of various **remote proctoring** solutions.
- Over 20 question types.
- Extensive options for exam configuration.
- Lockdown Client for Windows and Mac and SafeExamBrowser fully integrated.
- As cloud solution or own server.
- Preview and "tryout" questions and exams for authors.
- Advanced content administration.
- Reliable and stable in case of technical problems during an exam.
- [And much more...](https://www.bizexaminer.com/features/)

https://www.youtube.com/watch?v=PkGmyH4JEIQ

= Integrate bizExaminer into LearnDash =

With our LearnDash extension you can use all of the LMS features of LearnDash and handle quizzes in bizExaminer.

- **Create LearnDash quizzes** as you would normally. Set up prerequisites, associate the quiz with courses and lessons, design your certificates and use all of the LearnDash features you already use.
- **Connect a bizExaminer exam** with a quiz. When the user starts the quiz, he is automatically redirected to the bizExaminer exam and after he has finished the exam he will get back to the default LearnDash results view.
- **Certificates:** Use LearnDash's built-in certificates for quizzes or use the bizExaminer certificates function which integrates perfectly into LearnDash and allows the user to view his certificate everywhere via LearnDash's shortcodes and blocks.
- **Remote Proctoring:** bizExaminer supports Examity, Constructor (Examus), ProctorExam, Proctorio and Meazure Learning (ProctorU) for monitoring remote exams.
- **Results** are directly stored in LearnDash and therefore available to show in LearnDash's shortcodes, blocks, the user's profile etc.
- **LearnDash templates:** Our plugin uses LearnDash's templates directly and is therefore compatible with most themes and other third-party extensions.

Do you use special LearnDash features, other third-party extensions or have any **feature requests**? Let us know!

= Tested LearnDash Extensions and Themes =

Our plugin uses LearnDash's templates directly and is therefore compatible with most themes and other third-party extensions, but we especially made sure our plugin works with these plugins and themes:

- BuddyBoss + BuddyPress
- GamiPress

= Import Attempts from bizExaminer =

Our plugin provides the possibility to start and run exams in bizExaminer but still import their results into LearnDash.
A quiz has to explicitly enable the setting "Import Attempts from bizExaminer".
To allow users to import their results, the plugin includes two shortcodes:
1. `be_import_attempts_table`: A table for showing all quizzes of a course and allowing the user to import their results/attempts.
2. `be_import_attempts_button`: A button to allow the user to import their results/attempts for a single specific quiz.

If you want to use this feature and want more information, have a look at our [support article](https://support.bizexaminer.com/article/using-the-bizexaminer-learndash-extension/) or reach out to our support team.

== Installation ==

= Minimal Requirements =

- PHP 7.4 or newer
- WordPress 6.0 or newer
- LearnDash 4.3 or newer

= Automatic Installation =

We recommend installing the bizExaminer LearnDash extension through the WordPress Backend. Please install LearnDash before installing our plugin.

= Manual Installation =
1. Upload the contents of the plugin zip file to the `/wp-content/plugins/` directory.
2. Activate the plugin through the Plugins menu in WordPress.

= Setup =

1. Get your API credentials: Log into your bizExaminer instance as administrator and go to "Settings" > "Owner" / "Organisation" to copy your API credentials.
2. In WordPress go to "LearnDash" > "Settings" > "bizExaminer" and create a new set of API credentials.
3. Create a new quiz or select an existing one and enable the "bizExaminer" option in the quiz settings. You can then select which API credentials and which exam module to use.

For detailled information and screenshots have a look at our [support center](https://support.bizexaminer.com/article/using-the-bizexaminer-learndash-extension/).
== Frequently Asked Questions ==

= Do I need a bizExaminer Account? =

Yes. You can request a free demo on [our website](https://www.bizexaminer.com/#demo).

= Does this extension work with BuddyBoss or GamiPress? =

Yes, we tested our plugin with BuddyBoss and GamiPress.

For **GamiPress** all LearnDash related triggers work correctly, but only for automatically evaluated questions/exams.

If your exam has manually reviewed questions and you need GamiPress integration, please contact us.

= Need help? =

Have a look at our [support center](https://support.bizexaminer.com/article/using-the-bizexaminer-learndash-extension/).

You may ask your questions regarding the bizExaminer LearnDash extension within our free [WordPress Support Forum](https://wordpress.org/support/plugin/bizexaminer-learndash-extension).
Professional help desk support is being offered to bizExaminer customers only.

= Want to file a bug or improve the bizExaminer LearnDash extension? =
Bug reports may be filed via our WordPress support forum. If you have found security vulnerability, please [contact us](https://www.bizexaminer.com/contact/) immediately.

== Screenshots ==

1. Configure multiple bizExaminer instances and API credentials.
2. Connect a LearnDash quiz with a bizExaminer exam.
3. Use and configure a remote proctoring provider.
4. Users can start the exam just like normal LearnDash quizzes.
5. Users take the exam in bizExaminer.
6. Users can see their results directly in LearnDash.

== Changelog ==

= 1.5.2 (2024-10-31) =
- Compatibility: Tested with LearnDash 4.18.0.1 and WordPress 6.7

= 1.5.1 (2024-07-10) =
- Fix: Only show active/bookable exam modules in quiz settings
- Compatibility: Tested with LearnDash 4.15.2

= 1.5.0 (2024-05-13) =
- Compatibility: Tested with LearnDash 4.14.0 and 4.15.0
- Enhancement: Add Meazure Learning and Exmity v5 Remote Proctor support
- Enhancement: Show more detailled errors when starting exam files to make debuggin/support easier
- Dev: Added stricter checking of PHP compatibility and code rules/styles/linting

= 1.4.1 (2024-04-15) =
- Fix: Fix fatal error on PHP 7.4 installations

= 1.4.0 (2024-04-02) =
- Add: New feature to allow importing attempts from bizExaminer without starting them in LearnDash. See [documentation](https://support.bizexaminer.com/article/using-the-bizexaminer-learndash-extension/) for detailled information.

= 1.3.8 (2024-03-21) =
- Compatibility: Add "Tested up to" for WordPress 6.5
- Compatibility: Test with LearnDash 4.12.1
- i18n: Add new php translations file format (https://make.wordpress.org/core/2024/02/27/i18n-improvements-6-5-performant-translations/)

= 1.3.7 (2023-11-01) =
- Compatibility: Add "Tested up to" for WordPress 6.4
- Compatibility: Test with PHP 8.1

= 1.3.6 (2023-10-12) =
- Add: Examus Proctor - Add French language option
- Compatibility: Updated "Tested up to" for LearnDash to 4.9.1

= 1.3.5 (2023-09-20) =
- Fix: JS Error in quiz settings (leading to incompatible settings not being updated)
- Fix: Check for intl PHP extension before using IntlTimeZone
- Compatibility: Updated "Tested up to" for LearnDash to 4.9.0

= 1.3.4 (2023-08-25) =
- Fix: Fix non-ISO/unnamed timezones causing issues with starting exam. Added error message.
       Please configure a named ISO timezone in WordPress.
- Compatibility: Updated "Tested up to" for LearnDash 4.8.0

= 1.3.3 (2023-08-01) =
- Fix: Disabling bizExaminer Certificate on quiz not working
- Compatibility: Add "Tested up to" for WordPress 6.3

= 1.3.2 (2023-07-11) =
- Compatibility: Updated "Tested up to" for LearnDash 4.7.0.1

= 1.3.1 (2023-06-15) =
- Texts: Update UI labels for "Examus" to new "Constructor" name
- Tweak: Changed labels and new setting for Constructor proctor
- Fix: Fix result messages sometimes not showing with JavaScript/caching

= 1.3.0 (2023-06-05) =
- Dev: Add `bizexaminer/participantData` filter to allow chaning participant data sent to bizExaminer.

= 1.2.1 (2023-05-24) =
- Fix: Incompatible quiz settings not disabled
- Fix: Correct data sent to learndash_quiz_submitted hook
- Fix: GamiPress triggers depending on results not working
- Dev: Results are now fetched directly after returning/exam_finished callback as well

= 1.2.0 (2023-05-17) =
- Fix: Unable to resume exam or start a new one if the existing running attempt is not valid anymore (=expired)
- Fix: Send WordPress timezone to bizExaminer API
- Fix: Do not store exam url locally as per bizExaminer API docs
- Dev: Refactored QuizFrontend::renderQuiz to be more performant by preventing unnecessary data loading
- Dev: Added `bizexaminer/examValidDuration` filter to allow changing default valid duration (=validTo) of created bookings.

= 1.1.4 (2023-05-04) =
- Fix redirecting to old exams (showing bizExaminer overview) by disabling cache on quiz page and in callback API.

= 1.1.3 (2023-05-04) =
- Fix error with results having 0 score
- Easier enabling of LearnDash logging
- Remove custom `.htaccess` protection of logs (LearnDash core handles it)

= 1.1.2 (2023-05-03) =
- Tested LearnDash up to v4.5.3
- Fix errors/exceptions for pending results
- Fix "restart quiz" button on results view

= 1.1.1 (2023-04-04) =
Update for LearnDash 4.5.2 and WordPress 6.2

= 1.1.0 (2023-02-08) =
Update for LearnDash v4.5.0
- Use new LearnDash_Logger
- Use new LearnDash Site Health Data

= 1.0.1 (2022-12-05) =
Fix WordPress.org repo deployment and php lint error

= 1.0.0 (2022-12-05) =
First public release of the plugin. 🥳

== Upgrade Notice ==

= 1.0.0 =
No upgrade, just install.
