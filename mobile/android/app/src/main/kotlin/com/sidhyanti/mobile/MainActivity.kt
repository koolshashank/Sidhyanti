package com.sidhyanti.mobile

import io.flutter.embedding.android.FlutterFragmentActivity

// local_auth's BiometricPrompt requires a FragmentActivity -- plain
// FlutterActivity (the default template) doesn't support it.
class MainActivity : FlutterFragmentActivity()
