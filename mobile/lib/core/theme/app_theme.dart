import 'package:flutter/material.dart';

ThemeData buildLightTheme() {
  const scaffoldBg = Color(0xFFF5F5FB);
  return ThemeData(
    brightness: Brightness.light,
    colorSchemeSeed: const Color(0xFFE65100),
    useMaterial3: true,
    scaffoldBackgroundColor: scaffoldBg,
    appBarTheme: const AppBarTheme(
      backgroundColor: scaffoldBg,
      foregroundColor: Color(0xFF20223B),
      elevation: 0,
      surfaceTintColor: Colors.transparent,
    ),
    cardColor: Colors.white,
    dividerColor: const Color(0xFFE5E7EB),
    textTheme: ThemeData.light().textTheme.apply(bodyColor: const Color(0xFF20223B), displayColor: const Color(0xFF20223B)),
    inputDecorationTheme: const InputDecorationTheme(filled: true, fillColor: Color(0xFFF4F4FA)),
  );
}

ThemeData buildDarkTheme() {
  const scaffoldBg = Color(0xFF121319);
  return ThemeData(
    brightness: Brightness.dark,
    colorSchemeSeed: const Color(0xFFE65100),
    useMaterial3: true,
    scaffoldBackgroundColor: scaffoldBg,
    appBarTheme: const AppBarTheme(
      backgroundColor: scaffoldBg,
      foregroundColor: Color(0xFFEDEDF2),
      elevation: 0,
      surfaceTintColor: Colors.transparent,
    ),
    cardColor: const Color(0xFF1D1F27),
    dividerColor: const Color(0xFF2A2C36),
    textTheme: ThemeData.dark().textTheme.apply(bodyColor: const Color(0xFFEDEDF2), displayColor: const Color(0xFFEDEDF2)),
    inputDecorationTheme: const InputDecorationTheme(filled: true, fillColor: Color(0xFF262933)),
  );
}
