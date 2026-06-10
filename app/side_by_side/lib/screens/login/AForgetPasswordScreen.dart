// ignore_for_file: file_names
import 'package:flutter/material.dart';
import 'package:side_by_side/screens/login/ALoginScreen.dart';
import 'package:side_by_side/utils/AColors.dart';
import 'package:nb_utils/nb_utils.dart';
import 'package:side_by_side/main.dart';
import 'package:side_by_side/utils/AConstants.dart';
import 'package:side_by_side/utils/auth_service.dart';

class AForgetPasswordScreen extends StatefulWidget {
  const AForgetPasswordScreen({super.key});

  @override
  // ignore: library_private_types_in_public_api
  _AForgetPasswordScreenState createState() => _AForgetPasswordScreenState();
}

class _AForgetPasswordScreenState extends State<AForgetPasswordScreen> {
  final TextEditingController emailController = TextEditingController();

  @override
  void initState() {
    super.initState();
  }

  GlobalKey<FormState> myFormKey = GlobalKey<FormState>();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      resizeToAvoidBottomInset: false,
      body: Form(
        key: myFormKey,
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            children: [
              SizedBox(height: MediaQuery.of(context).viewPadding.top),
              Row(
                mainAxisAlignment: MainAxisAlignment.start,
                children: [
                  ClipRRect(
                    borderRadius: BorderRadius.circular(25),
                    child: Container(
                      width: 50,
                      height: 50,
                      color:
                          appStore.isDarkModeOn
                              ? appColorPrimary
                              : appColorSecondary,
                      child: InkWell(
                        borderRadius: BorderRadius.circular(25),
                        child: Icon(
                          Icons.arrow_back_ios_outlined,
                          color:
                              appStore.isDarkModeOn ? black : appTextColorWhite,
                        ),
                        onTap:
                            () => Navigator.push(
                              context,
                              MaterialPageRoute(
                                builder: (context) => ALoginScreen(),
                              ),
                            ),
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 60),
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Recuperar Senha',
                    style: TextStyle(fontSize: 40, fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 16),
                  const Text('Digite o email cadastrado'),
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 16.0),
                    child: ClipRRect(
                      borderRadius: BorderRadius.circular(15),
                      child: TextFormField(
                        textInputAction: TextInputAction.next,
                        controller: emailController,
                        style: colorPrimaryRegular16,
                        autovalidateMode: AutovalidateMode.always,
                        keyboardType: TextInputType.emailAddress,
                        decoration: InputDecoration(
                          fillColor: appColorPrimary,
                          filled: true,
                          border: InputBorder.none,
                          labelStyle: colorSecondaryBold14,
                          label: const Text('E-mail'),
                          hintText: 'Digite seu e-mail',
                          hintStyle: const TextStyle(color: Colors.grey),
                        ),
                      ),
                    ),
                  ),
                ],
              ),
              const Spacer(),
              Align(
                alignment: Alignment.bottomRight,
                child: SizedBox(
                  height: 60,
                  width: 200,
                  child: ElevatedButton(
                    onPressed: () async {
                      if (myFormKey.currentState!.validate()) {
                        if (emailController.text == '') {
                          ScaffoldMessenger.of(context).showSnackBar(
                            SnackBar(
                              content: Text(
                                'Digite um e-mail válido',
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
                          AuthService.resetPassword(emailController.text).then((
                            value,
                          ) async {
                            ScaffoldMessenger.of(context).showSnackBar(
                              SnackBar(
                                content: Text(
                                  value,
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
                          });
                        }
                      }
                    },
                    style: ElevatedButton.styleFrom(
                      backgroundColor:
                          appStore.isDarkModeOn
                              ? appColorPrimary
                              : appColorSecondary,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(15),
                      ),
                    ),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Text(
                          'Enviar',
                          style:
                              appStore.isDarkModeOn
                                  ? colorPrimaryBold20
                                  : colorWhiteBold20,
                        ),
                        SizedBox(width: 10),
                        Icon(
                          Icons.arrow_forward_ios_outlined,
                          size: 15,
                          color:
                              appStore.isDarkModeOn ? black : appTextColorWhite,
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
