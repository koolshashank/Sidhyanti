import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/api/api_exception.dart';
import '../../core/auth/auth_state.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> with SingleTickerProviderStateMixin {
  final _formKey = GlobalKey<FormState>();
  final _usernameController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _loading = false;
  String? _error;
  bool _obscurePassword = true;

  late final AnimationController _animController;
  late final Animation<double> _fade;
  late final Animation<Offset> _slide;

  static const _gradientStart = Color(0xFF3A3D9F);
  static const _gradientEnd = Color(0xFF6A4FC7);

  @override
  void initState() {
    super.initState();
    _animController = AnimationController(vsync: this, duration: const Duration(milliseconds: 700));
    _fade = CurvedAnimation(parent: _animController, curve: Curves.easeOut);
    _slide = Tween<Offset>(begin: const Offset(0, 0.08), end: Offset.zero)
        .animate(CurvedAnimation(parent: _animController, curve: Curves.easeOutCubic));
    _animController.forward();
  }

  @override
  void dispose() {
    _animController.dispose();
    _usernameController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      await context.read<AuthState>().login(_usernameController.text.trim(), _passwordController.text);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final authState = context.watch<AuthState>();
    final sessionMessage = authState.sessionMessage;
    final size = MediaQuery.of(context).size;

    return Scaffold(
      backgroundColor: _gradientEnd,
      body: Stack(
        children: [
          // Gradient backdrop.
          Positioned.fill(
            child: DecoratedBox(
              decoration: const BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: [_gradientStart, _gradientEnd],
                ),
              ),
            ),
          ),
          // Soft decorative glows for depth.
          Positioned(
            top: -size.width * 0.35,
            right: -size.width * 0.3,
            child: _Glow(diameter: size.width * 0.9, color: Colors.white.withValues(alpha: 0.08)),
          ),
          Positioned(
            bottom: -size.width * 0.4,
            left: -size.width * 0.35,
            child: _Glow(diameter: size.width * 0.85, color: Colors.white.withValues(alpha: 0.06)),
          ),
          Positioned(
            top: size.height * 0.14,
            left: -size.width * 0.15,
            child: _Glow(diameter: size.width * 0.4, color: Colors.amberAccent.withValues(alpha: 0.10)),
          ),
          SafeArea(
            child: FadeTransition(
              opacity: _fade,
              child: SlideTransition(
                position: _slide,
                child: LayoutBuilder(
                  builder: (context, constraints) {
                    return SingleChildScrollView(
                      padding: const EdgeInsets.symmetric(horizontal: 24),
                      child: ConstrainedBox(
                        constraints: BoxConstraints(minHeight: constraints.maxHeight),
                        child: IntrinsicHeight(
                          child: Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            crossAxisAlignment: CrossAxisAlignment.center,
                            children: [
                              const SizedBox(height: 24),
                              _Brand(),
                              const SizedBox(height: 36),
                              ConstrainedBox(
                                constraints: const BoxConstraints(maxWidth: 420),
                                child: _GlassCard(
                                  child: Form(
                                    key: _formKey,
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.stretch,
                                      children: [
                                        Text(
                                          'Welcome back',
                                          style: Theme.of(context).textTheme.titleLarge?.copyWith(
                                                fontWeight: FontWeight.w700,
                                                color: const Color(0xFF20223B),
                                              ),
                                        ),
                                        const SizedBox(height: 4),
                                        Text(
                                          'Log in to manage your members & collections',
                                          style: TextStyle(color: Colors.grey.shade600, fontSize: 13),
                                        ),
                                        const SizedBox(height: 24),
                                        if (sessionMessage != null) ...[
                                          _Banner(text: sessionMessage),
                                          const SizedBox(height: 16),
                                        ],
                                        _RoundedField(
                                          controller: _usernameController,
                                          label: 'Username',
                                          icon: Icons.person_outline_rounded,
                                          textInputAction: TextInputAction.next,
                                          validator: (v) => (v == null || v.trim().isEmpty) ? 'Username is required' : null,
                                        ),
                                        const SizedBox(height: 16),
                                        _RoundedField(
                                          controller: _passwordController,
                                          label: 'Password',
                                          icon: Icons.lock_outline_rounded,
                                          obscureText: _obscurePassword,
                                          textInputAction: TextInputAction.done,
                                          onFieldSubmitted: (_) => _submit(),
                                          suffixIcon: IconButton(
                                            icon: Icon(
                                              _obscurePassword ? Icons.visibility_outlined : Icons.visibility_off_outlined,
                                              color: Colors.grey.shade500,
                                            ),
                                            onPressed: () => setState(() => _obscurePassword = !_obscurePassword),
                                          ),
                                          validator: (v) => (v == null || v.isEmpty) ? 'Password is required' : null,
                                        ),
                                        if (_error != null) ...[
                                          const SizedBox(height: 14),
                                          Row(
                                            children: [
                                              Icon(Icons.error_outline_rounded, size: 16, color: Colors.red.shade400),
                                              const SizedBox(width: 6),
                                              Expanded(
                                                child: Text(_error!, style: TextStyle(color: Colors.red.shade400, fontSize: 13)),
                                              ),
                                            ],
                                          ),
                                        ],
                                        const SizedBox(height: 24),
                                        _GradientButton(loading: _loading, onPressed: _submit),
                                      ],
                                    ),
                                  ),
                                ),
                              ),
                              const SizedBox(height: 28),
                              Text(
                                'SIDHYANTI FINANCIAL SERVICES',
                                style: TextStyle(
                                  color: Colors.white.withValues(alpha: 0.55),
                                  fontSize: 11,
                                  letterSpacing: 2,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                              const SizedBox(height: 16),
                            ],
                          ),
                        ),
                      ),
                    );
                  },
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _Brand extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Container(
          width: 84,
          height: 84,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            boxShadow: [
              BoxShadow(color: Colors.black.withValues(alpha: 0.25), blurRadius: 24, offset: const Offset(0, 10)),
            ],
          ),
          child: ClipOval(
            child: Image.asset('assets/icon/app_logo.png', fit: BoxFit.cover),
          ),
        ),
        const SizedBox(height: 18),
        const Text(
          'RDMCS',
          style: TextStyle(color: Colors.white, fontSize: 26, fontWeight: FontWeight.w800, letterSpacing: 0.3),
        ),
      ],
    );
  }
}

class _GlassCard extends StatelessWidget {
  final Widget child;

  const _GlassCard({required this.child});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(24, 28, 24, 24),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(28),
        boxShadow: [
          BoxShadow(color: Colors.black.withValues(alpha: 0.22), blurRadius: 32, offset: const Offset(0, 16)),
        ],
      ),
      child: child,
    );
  }
}

class _Banner extends StatelessWidget {
  final String text;

  const _Banner({required this.text});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: Colors.orange.shade50,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: Colors.orange.shade200),
      ),
      child: Row(
        children: [
          Icon(Icons.info_outline_rounded, size: 16, color: Colors.orange.shade800),
          const SizedBox(width: 8),
          Expanded(child: Text(text, style: TextStyle(color: Colors.orange.shade900, fontSize: 12.5))),
        ],
      ),
    );
  }
}

class _RoundedField extends StatelessWidget {
  final TextEditingController controller;
  final String label;
  final IconData icon;
  final bool obscureText;
  final Widget? suffixIcon;
  final TextInputAction? textInputAction;
  final ValueChanged<String>? onFieldSubmitted;
  final String? Function(String?) validator;

  const _RoundedField({
    required this.controller,
    required this.label,
    required this.icon,
    required this.validator,
    this.obscureText = false,
    this.suffixIcon,
    this.textInputAction,
    this.onFieldSubmitted,
  });

  @override
  Widget build(BuildContext context) {
    // This card is always a white "glass" surface by design, regardless of
    // the device's light/dark theme (see _GlassCard) -- so its text must
    // stay a fixed dark color too. Relying on the theme's default text
    // color made typed text nearly invisible in dark mode, where that
    // color is a near-white meant for dark backgrounds elsewhere in the app.
    return TextFormField(
      controller: controller,
      obscureText: obscureText,
      textInputAction: textInputAction,
      onFieldSubmitted: onFieldSubmitted,
      validator: validator,
      style: const TextStyle(fontSize: 15, color: Color(0xFF20223B)),
      cursorColor: const Color(0xFF6A4FC7),
      decoration: InputDecoration(
        labelText: label,
        labelStyle: TextStyle(color: Colors.grey.shade600),
        floatingLabelStyle: const TextStyle(color: Color(0xFF6A4FC7)),
        prefixIcon: Icon(icon, size: 20, color: Colors.grey.shade500),
        suffixIcon: suffixIcon,
        filled: true,
        fillColor: const Color(0xFFF4F4FA),
        contentPadding: const EdgeInsets.symmetric(vertical: 16, horizontal: 16),
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: BorderSide.none),
        enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: BorderSide.none),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: Color(0xFF6A4FC7), width: 1.6),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: BorderSide(color: Colors.red.shade300, width: 1.2),
        ),
      ),
    );
  }
}

class _GradientButton extends StatelessWidget {
  final bool loading;
  final VoidCallback onPressed;

  const _GradientButton({required this.loading, required this.onPressed});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 52,
      child: DecoratedBox(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(16),
          gradient: const LinearGradient(
            colors: [Color(0xFF6A4FC7), Color(0xFF3A3D9F)],
          ),
          boxShadow: [
            BoxShadow(color: const Color(0xFF6A4FC7).withValues(alpha: 0.4), blurRadius: 16, offset: const Offset(0, 8)),
          ],
        ),
        child: Material(
          color: Colors.transparent,
          child: InkWell(
            borderRadius: BorderRadius.circular(16),
            onTap: loading ? null : onPressed,
            child: Center(
              child: loading
                  ? const SizedBox(
                      height: 22,
                      width: 22,
                      child: CircularProgressIndicator(strokeWidth: 2.4, color: Colors.white),
                    )
                  : const Text(
                      'Log In',
                      style: TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w700, letterSpacing: 0.4),
                    ),
            ),
          ),
        ),
      ),
    );
  }
}

class _Glow extends StatelessWidget {
  final double diameter;
  final Color color;

  const _Glow({required this.diameter, required this.color});

  @override
  Widget build(BuildContext context) {
    return IgnorePointer(
      child: Container(
        width: diameter,
        height: diameter,
        decoration: BoxDecoration(shape: BoxShape.circle, color: color),
      ),
    );
  }
}
