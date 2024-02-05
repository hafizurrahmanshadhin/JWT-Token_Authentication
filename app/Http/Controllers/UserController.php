<?php

namespace App\Http\Controllers;

use App\Helper\JWTToken;
use App\Mail\OTPMail;
use App\Models\User;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class UserController extends Controller {
    function HomePage(): View {
        return view('pages.home');
    }
    function LoginPage(): View {
        return view('pages.auth.login-page');
    }
    function RegistrationPage(): View {
        return view('pages.auth.registration-page');
    }
    function SendOtpPage(): View {
        return view('pages.auth.send-otp-page');
    }
    function VerifyOTPPage(): View {
        return view('pages.auth.verify-otp-page');
    }
    function ResetPasswordPage(): View {
        return view('pages.auth.reset-pass-page');
    }
    function ProfilePage(): View {
        return view('pages.dashboard.profile-page');
    }

    function UserRegistration(Request $request): JsonResponse {
        try {
            $request->validate([
                'firstName' => 'required|string|max:25|min:1',
                'lastName'  => 'required|string|max:25|min:1',
                'email'     => 'required|string|email|max:50|min:7|unique:users,email',
                'mobile'    => 'required|string|max:20|min:7',
                'password'  => 'required|string|max:1000|min:3',
            ]);

            User::create([
                'firstName' => $request->input('firstName'),
                'lastName'  => $request->input('lastName'),
                'email'     => $request->input('email'),
                'mobile'    => $request->input('mobile'),
                //! For Without Encryption
                // 'password'  => $request->input('password'),
                'password'  => Hash::make($request->input('password')),
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'User Registered successfully',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'fail',
                'message' => $e->getMessage(),
            ]);
        }
    }

    function UserLogin(Request $request) {
        try {
            $request->validate([
                'email'    => 'required|string|email|max:50|min:7',
                'password' => 'required|string|max:1000|min:3',
            ]);

            //! For Without Encryption
            //* $count = User::where('email', '=', $request->input('email'))
            //*     ->where('password', '=', $request->input('password'))
            //*     ->select('id')->first();

            $user = User::where('email', '=', $request->input('email'))->first();

            //! ($count != null) [if condition for without encryption]
            if ($user != null && Hash::check($request->input('password'), $user->password)) {
                //! User Login -> JWT Token Issue
                //? $token = JWTToken::CreateToken($request->input('email'), $count->id); // Without Encryption
                $token = JWTToken::CreateToken($request->input('email'), $user->id);
                return response()->json([
                    'status'  => 'success',
                    'message' => 'Login Successfully',
                ])->cookie('token', $token, time() + 60 * 24 * 30);
            } else {
                return response()->json([
                    'status'  => 'fail',
                    'message' => 'Invalid email or password',
                ]);
            }
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'fail',
                'message' => $e->getMessage(),
            ]);
        }
    }

    function UserProfile(Request $request): JsonResponse {
        $email = $request->header('email');
        $user  = User::where('email', '=', $email)->first();
        return response()->json([
            'status'  => 'success',
            'message' => 'Request Successful',
            'data'    => $user,
        ]);
    }

    function UserLogout(): RedirectResponse {
        return redirect('/userLogin')->cookie('token', '', -1);
    }

    function UpdateProfile(Request $request): JsonResponse {
        try {
            $request->validate([
                'firstName' => 'string|max:25|min:1',
                'lastName'  => 'string|max:25|min:1',
                'mobile'    => 'string|max:20|min:6',
                'password'  => 'string|max:1000|min:3',
            ]);

            $email     = $request->header('email');
            $firstName = $request->input('firstName');
            $lastName  = $request->input('lastName');
            $mobile    = $request->input('mobile');
            // $password  = $request->input('password');

            User::where('email', '=', $email)->update([
                'firstName' => $firstName,
                'lastName'  => $lastName,
                'mobile'    => $mobile,
                // 'password'  => $password,
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Profile Updated Successfully',
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status'  => 'fail',
                'message' => $e->getMessage(),
            ]);
        }
    }

    function SendOTPCode(Request $request): JsonResponse {
        try {
            $request->validate([
                'email' => 'required|string|email|max:50|min:7',
            ]);

            $email = $request->input('email');
            $otp   = rand(1000, 9999);
            $count = User::where('email', '=', $email)->count();

            if ($count == 1) {
                //! OTP Email Address
                Mail::to($email)->send(new OTPMail($otp));
                //! OTP Code Database Table Update
                User::where('email', '=', $email)->update([
                    'otp' => $otp,
                ]);

                return response()->json([
                    'status'  => 'success',
                    'message' => 'OTP Sent Successfully',
                ]);
            } else {
                return response()->json([
                    'status'  => 'fail',
                    'message' => 'Invalid Email Address',
                ], 401);
            }
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'fail',
                'message' => $e->getMessage(),
            ]);
        }
    }

    function VerifyOTP(Request $request) {
        try {
            $request->validate([
                'email' => 'required|string|email|max:50|min:7',
                'otp'   => 'required|string|max:4|min:4',
            ]);

            $email = $request->input('email');
            $otp   = $request->input('otp');

            $count = User::where('email', '=', $email)
                ->where('otp', '=', $otp)
                ->count();

            if ($count == 1) {
                //! Database OTP Update
                User::where('email', '=', $email)->update([
                    'otp' => '0',
                ]);
                //! Password Reset Token Issue
                $token = JWTToken::CreateTokenForSetPassword($request->input('email'));
                return response()->json([
                    'status'  => 'success',
                    'message' => 'OTP Verification Successful',
                ])->cookie('token', $token, time() + 60 * 24 * 30);
            } else {
                return response()->json([
                    'status'  => 'fail',
                    'message' => 'Invalid OTP',
                ], 401);
            }
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'fail',
                'message' => $e->getMessage(),
            ]);
        }
    }

    function ResetPassword(Request $request): JsonResponse {
        try {
            $request->validate([
                'password' => 'required|string|max:1000|min:3',
            ]);

            $email    = $request->header('email');
            $password = Hash::make($request->input('password'));

            User::where('email', '=', $email)->update(['password' => $password]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Password Reset Successfully',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'fail',
                'message' => $e->getMessage(),
            ]);
        }
    }
}
