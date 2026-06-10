import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:side_by_side/main.dart';
import 'package:side_by_side/utils/AColors.dart';
import 'package:side_by_side/utils/AConstants.dart';

class NotificationPermissionHelper {
  /// Checa permissão de notificações
  static Future<void> checkAndRequestPermission(BuildContext context) async {
    if (kIsWeb) {
      _handleWebPermission(context);
      return;
    }

    // 📱 Mobile (Android/iOS)
    final status = await Permission.notification.status;

    if (status.isDenied || status.isPermanentlyDenied) {
      _showPermissionDialog(context);
    } else if (status.isGranted) {
      debugPrint("✅ Notificações já permitidas");
    } else {
      final result = await Permission.notification.request();
      if (result.isDenied || result.isPermanentlyDenied) {
        _showPermissionDialog(context);
      }
    }
  }

  /// 🔔 Web (navegador)
  static void _handleWebPermission(BuildContext context) {
    final scaffoldMessenger = ScaffoldMessenger.of(context);

    scaffoldMessenger.showSnackBar(
      SnackBar(
        content: Text(
          "Você bloqueou as notificações no navegador.\n"
          "👉 Clique no cadeado ao lado da URL e ative manualmente.",
          style:
              appStore.isDarkModeOn
                  ? colorPrimaryRegular16
                  : colorWhiteRegular16,
        ),
        backgroundColor:
            appStore.isDarkModeOn ? appColorPrimary : appColorSecondary,
        duration: Duration(seconds: 6),
      ),
    );
  }

  /// 📱 Dialog para Mobile
  static void _showPermissionDialog(BuildContext context) {
    showDialog(
      context: context,
      builder:
          (ctx) => AlertDialog(
            backgroundColor:
                appStore.isDarkModeOn ? appColorPrimary : appColorSecondary,
            title: Text(
              "Permitir notificações",
              style:
                  appStore.isDarkModeOn
                      ? colorPrimarySemiBold16
                      : colorWhiteSemiBold16,
            ),
            content: Text(
              "Você recusou as notificações.\n"
              "Para ativar, abra as configurações do app.",
              style:
                  appStore.isDarkModeOn
                      ? colorPrimaryRegular14
                      : colorWhiteRegular14,
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(ctx),
                child: const Text("Cancelar"),
              ),
              ElevatedButton(
                onPressed: () async {
                  Navigator.pop(ctx);
                  await openAppSettings(); // 🔥 abre as configurações do app
                },
                child: const Text("Abrir Configurações"),
              ),
            ],
          ),
    );
  }
}
