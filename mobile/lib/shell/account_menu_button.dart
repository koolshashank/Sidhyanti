import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../core/auth/auth_state.dart';

class AccountMenuButton extends StatelessWidget {
  const AccountMenuButton({super.key});

  @override
  Widget build(BuildContext context) {
    final staff = context.watch<AuthState>().currentStaff;
    return IconButton(
      tooltip: staff?.fullName,
      onPressed: () => context.push('/account'),
      icon: const CircleAvatar(child: Icon(Icons.person)),
    );
  }
}
