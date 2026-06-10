// ignore_for_file: file_names

import 'package:firebase_auth/firebase_auth.dart';
import 'package:nb_utils/nb_utils.dart';
import 'package:provider/provider.dart';
import 'package:side_by_side/main.dart';
import 'package:side_by_side/model/pg.dart';
import 'package:side_by_side/model/usuario.dart';
import 'package:flutter/material.dart';
import 'package:side_by_side/screens/login/ALoginScreen.dart';
import 'package:side_by_side/utils/AColors.dart';
import 'package:side_by_side/utils/AConstants.dart';
import 'package:side_by_side/utils/auth_service.dart';

class ACriancasAddComponent extends StatefulWidget {
  const ACriancasAddComponent({super.key});

  @override
  State<ACriancasAddComponent> createState() => _ACriancasAddComponentState();
}

class _ACriancasAddComponentState extends State<ACriancasAddComponent> {
  final _nameController = TextEditingController();
  final _ageController = TextEditingController();

  Future<bool> verificarLogin() async {
    User? userFire = AuthService.gerarUserFirebase();

    if (userFire == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            'Faça o login novamente',
            style:
                appStore.isDarkModeOn
                    ? colorPrimaryRegular16
                    : colorWhiteRegular16,
          ),
          backgroundColor:
              appStore.isDarkModeOn ? appColorPrimary : appColorSecondary,
        ),
      );

      await Future.delayed(const Duration(seconds: 2));

      if (!mounted) return false;

      Navigator.pushReplacement(
        context,
        MaterialPageRoute(builder: (context) => ALoginScreen()),
      );

      return false;
    }

    return true;
  }

  @override
  Widget build(BuildContext context) {
    Usuario usuario =
        Provider.of<UsuarioProvider>(context, listen: false).getUsuario;
    Pg pg = Provider.of<PgProvider>(context, listen: false).getPg;
    return Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(height: MediaQuery.of(context).viewPadding.top),
          const SizedBox(height: 64),
          const Text(
            "Nome",
            style: TextStyle(fontWeight: FontWeight.w600, fontSize: 30),
          ),
          ClipRRect(
            borderRadius: BorderRadius.circular(15),
            child: TextField(
              controller: _nameController,
              style: colorPrimaryRegular16,
              decoration: InputDecoration(
                border: InputBorder.none,
                fillColor: appColorPrimary,
                filled: true,
                labelStyle: colorSecondaryBold14,
                hintStyle: colorSecondaryBold14,
                labelText: 'Nome',
                hintText: 'Informe o nome',
              ),
            ),
          ),
          const SizedBox(height: 16),
          const Text(
            "Idade",
            style: TextStyle(fontWeight: FontWeight.w600, fontSize: 30),
          ),
          ClipRRect(
            borderRadius: BorderRadius.circular(15),
            child: TextField(
              controller: _ageController,
              style: colorPrimaryRegular16,
              keyboardType: TextInputType.number,
              decoration: InputDecoration(
                border: InputBorder.none,
                fillColor: appColorPrimary,
                filled: true,
                labelStyle: colorSecondaryBold14,
                hintStyle: colorSecondaryBold14,
                labelText: 'Idade',
                hintText: 'Informe a Idade',
              ),
            ),
          ),
          const Spacer(),
          ElevatedButton(
            onPressed: () async {
              bool podeContinuar = await verificarLogin();

              if (!podeContinuar) {
                return;
              }
              if (_nameController.text == '' || _ageController.text == '') {
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    content: Text(
                      'Informações vazias',
                      style:
                          appStore.isDarkModeOn
                              ? colorPrimaryRegular16
                              : colorWhiteRegular16,
                    ),
                    backgroundColor:
                        appStore.isDarkModeOn
                            ? appColorPrimary
                            : appColorSecondary,
                  ),
                );
              } else {
                await storePg
                    .addCriancas(
                      usuario.uid,
                      pg.id,
                      _nameController.text,
                      _ageController.text,
                    )
                    .then((value) {
                      if (value) {
                        _nameController.text = '';
                        _ageController.text = '';
                        //sucesso
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(
                            content: Text(
                              'Sucesso',
                              style:
                                  appStore.isDarkModeOn
                                      ? colorPrimaryRegular16
                                      : colorWhiteRegular16,
                            ),
                            backgroundColor:
                                appStore.isDarkModeOn
                                    ? appColorPrimary
                                    : appColorSecondary,
                          ),
                        );
                      } else {
                        // algum erro//sucesso
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(
                            content: Text(
                              'Erro',
                              style:
                                  appStore.isDarkModeOn
                                      ? colorPrimaryRegular16
                                      : colorWhiteRegular16,
                            ),
                            backgroundColor:
                                appStore.isDarkModeOn
                                    ? appColorPrimary
                                    : appColorSecondary,
                          ),
                        );
                      }
                    });
              }
            },
            style: ElevatedButton.styleFrom(
              backgroundColor:
                  appStore.isDarkModeOn ? appColorPrimary : appColorSecondary,
              padding: const EdgeInsets.symmetric(horizontal: 48, vertical: 16),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(15),
              ),
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(
                  'Salvar',
                  style:
                      appStore.isDarkModeOn
                          ? colorPrimaryBold20
                          : colorWhiteBold20,
                ),
                const SizedBox(width: 8),
                Icon(
                  Icons.check_circle_outline,
                  size: 17,
                  color: appStore.isDarkModeOn ? black : appTextColorWhite,
                ),
              ],
            ),
          ),
          const Spacer(),
        ],
      ),
    );
  }
}
