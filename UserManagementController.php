<?php

namespace App\Http\Controllers\UserManagement;

use App\Exports\UsersExport;
use App\Helpers\LogActivityHelper;
use App\Http\Controllers\Controller;
use App\Models\SubscriptionNew;
use App\Models\User;
use App\Models\CustomerWallet;
use App\Models\OutstandingTrans;
use App\Models\Role;
use App\Models\CustomerInvoices;
use App\Models\BarclaycardCustomerPaymentTokens;
use App\Models\LogActivity;
use App\Models\URMPermissions;
use App\Models\DidList;
use App\Models\SubscriptionPackages;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Auth;
use App\Models\NetworkDetails;
use Spatie\Permission\Models\Permission;
use stdClass;
use App\Models\CliNumbers;
use App\Models\SpeedDials;
use App\Models\SipConnectionHistory;
use PDF;
use Validator;
use App\Models\SipAccountPinRegenerations;
use App\Models\SipAccountSipRegenerations;
use Illuminate\Support\Facades\DB;
use App\Models\Gateways;
use App\Models\Ports;
use App\Models\AllocatedPorts;
use App\Models\CustomerDisconnectInfo;
use App\Models\IncomingForwardingNumbers;
use App\Models\OutstandingPaymentReminderMails;
use App\Models\PortAssignHistory;
use App\Models\SipPrefix;
use App\Models\PaymentOptions;
use App\Models\InvoiceSubscriptionDetails;
use App\Models\SipTopupHistory;
use Illuminate\Support\Facades\Http;
use App\Models\FailedSipTopupsUsingToken;
use App\Models\SuccessSipTopupsUsingToken;
use App\Models\SuccessSipAutoTopupsCron;
use App\Models\FailedSipAutoTopupsCron;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Models\CustomerRecurringTemplates;
use App\Models\RecurredInvoices;
use App\Models\SipAccountSuspends;
use App\Models\DidRoutes;
use App\Models\DidSuppliers;
use App\Models\CustomerDids;
use App\Models\GlobalTopupAmount;

class UserManagementController extends Controller
{
    public function Index()
    {
        //        $roles = Role::getAllRoles();
        //        $data['roles']=$roles;
        $users = User::getAllUsers();
        $data['users'] = $users;
        //        return view('layouts.showusers',$data);

        return view('layouts.showusers', $data);
    }

    public function generateUserPDF(Request $request)
    {
        $value = User::all();
        $data = [];
        array_push($data, $value);

        // share data to view
        view()->share('users', $data);
        $pdf = PDF::loadView('user-pdf', $data);
        // download PDF file with download method
        return $pdf->download('users.pdf');
    }
    public function export()
    {
        return Excel::download(new UsersExport, 'users.xlsx');
    }



    public function addUser()
    {
        //        $roles = Role::getAllRoles();
        //        $data['roles']=$roles;
        $users = User::getAllUsers();
        $data['users'] = $users;
        //        return view('layouts.showusers',$data);

        //        return view('layouts.showusers',$data);
        return view('layouts.admin-register-user', $data);
    }

    public function getUserDetails(Request $request)
    {
        if (isset($request->hdnPKey)) {
            $dataset = new stdClass();
            $dataset->id = $request->hdnPKey;
            $userDetails = User::getUsersDetails($dataset);
            return json_encode([
                "status" => '200',
                "message" => $userDetails
            ]);
        }
    }

    public function viewUserEditAdmin(Request $request)
    {
        try {
            $hdnPKey = $request->route('id');
            $dataset = new \stdClass();
            $dataset->id = $hdnPKey;

            // Retrieve specific user details based on $hdnPKey
            $userDetails = User::getUsersDetails($dataset);

            // Retrieve all users from the users table
            $allUsers = User::all();

            // Find the user with id 3 in the collection
            $userWithId3 = $allUsers->firstWhere('id', $hdnPKey);

            // Dump and die the result
            // dd($userWithId3);

            // Pass the user details and all users to the view
            return view('frontend.view-user-edit-admin', ['user_details' => $userDetails, 'user_with_id_3' => $userWithId3, 'all_users' => $allUsers]);
        } catch (\Exception $e) {
            return abort(500, 'Error retrieving user data.'); // You can customize this error response
        }
    }

    public function disconnectUser($id, Request $request)
    {
        $user = User::find($id);

        if ($user) {
            $disconnectInfo = new CustomerDisconnectInfo;
            $disconnectInfo->customer_id = $user->id;
            $disconnectInfo->is_active = 0;
            $disconnectInfo->reason = $request->input('reason');
            $disconnectInfo->disconnected_by = Auth::id();
            $disconnectInfo->disconnected_date = $request->input('disconnect_date');
            $disconnectInfo->save();

            $user->update([
                'is_active' => 0,
            ]);

            session()->flash('success', 'Customer Disconnected successfully.');
        } else {
            session()->flash('error', 'User not found.');
        }

        return redirect()->back();
    }

    public function reconnectUser($id)
    {
        // Find the user by ID
        $user = User::find($id);

        if ($user) {
            // Update the columns is_active and record_status to 0
            $user->update([
                'is_active' => 1,
            ]);

            // Optionally, you can add a success message or perform other actions after updating
            // Example flash message (requires session handling)
            session()->flash('success', 'Customer Reconnected successfully');
        } else {
            // Handle case where user is not found
            // You might want to display an error message or redirect with a message
            session()->flash('error', 'User not found.');
        }

        // Redirect back or to a specific page after updating status
        return redirect()->back();
    }


    public function viewUserEdit(Request $request)
    {
        try {
            $hdnPKey = $request->route('id');
            $dataset = new \stdClass();
            $dataset->id = $hdnPKey;

            // Retrieve specific user details based on $hdnPKey
            $userDetails = User::getUsersDetails($dataset);

            // Retrieve all users from the users table
            $allUsers = User::all();

            // Find the user with id 3 in the collection
            $userWithId3 = $allUsers->firstWhere('id', $hdnPKey);

            // Dump and die the result
            // dd($userWithId3);

            // Pass the user details and all users to the view
            return view('frontend.view-user-edit', ['user_details' => $userDetails, 'user_with_id_3' => $userWithId3, 'all_users' => $allUsers]);
        } catch (\Exception $e) {
            return abort(500, 'Error retrieving user data.'); // You can customize this error response
        }
    }




    //    public function viewUserEdit(Request $request) {
    //        try {
    //            $hdnPKey = $request->route('id');
    //            $data['hdnPKey'] = $hdnPKey;
    //
    //            $dataset = new stdClass();
    //            $dataset->id = $hdnPKey;
    //
    //            $userDetails = User::getUsersDetails($dataset);
    //
    //            // Check if user details are not empty before assigning to the data array
    //            if (!empty($userDetails)) {
    //                $data['user_details'] = $userDetails;
    //            } else {
    //                // If user details are empty, you can handle it accordingly
    //                // For example, redirect to an error page or show a custom message
    //                return redirect()->route('error.page')->with('error', 'User details not found');
    //            }
    //            dd( $data);
    //            return view('frontend.view-user-edit', $data);
    //        } catch (\Exception $e) {
    //            // Log the exception or handle it in a way that suits your application
    //            return redirect()->route('error.page')->with('error', 'An error occurred');
    //        }
    //    }


    public function viewAddUser()
    {
        return view('layouts.admin-register-user');
    }

    public function setUserAccount(Request $request)
    {
        if (isset($request->hdnPKey)) {
            $input = $request->all();
            dd($input);
            if ($image = $request->file('avatar')) {
                $destinationPath = storage_path('profile-photos');
                $profileImage = date('YmdHis') . "." . $image->getClientOriginalExtension();
                $image->move($destinationPath, $profileImage);
                dd($profileImage);
                //                $input['avatar'] = "$profileImage";
            } else {
                unset($input['avatar']);
            }




            $profile = User::where(['id' => $request->hdnPKey])->first();
            $profile->title = $request->cmbTitle;
            $profile->first_name = $request->txtFname;
            $profile->last_name = $request->txtLname;
            $profile->gender = $request->gender;
            $profile->account_type = $request->cmbAccountType;
            $profile->business_name = $request->txtCompanyName;
            $profile->fixed_phone = $request->txtFixedPhoneNo;
            $profile->mobile_no = $request->txtMobilePhone;
            $profile->address_line1 = $request->txtAddress;
            $profile->address_line2 = $request->txtAddress2;
            $profile->address_line3 = $request->txtAddress3;
            $profile->post_code = $request->txtCity;
            $profile->area = $request->txtPostalCode;
            $profile->country = $request->txtCountry;
            $profile->update();



            return json_encode([
                "status" => '200',
                "message" => "Account Successfully Updated"
            ]);
        }
    }

    //    public function updateUserAccount(Request $request){
    //
    //       // dd($request->all());
    //        $profile = User::where(['id' => $request->hdnPKey])->first();
    //        $picture = $request->file('main_image');
    //        if ($picture) {
    //            $profile->profile_photo_path = $this->imageUploader($picture, 'main');
    //        }
    //        $password = $request->current_password;
    //        $new_password = $request->new_password;
    //        $confirm_password = $request->confirm_password;
    //
    //        if ($password && $new_password && $confirm_password) {
    //           $check= Hash::check($password, auth()->user()->password);
    ////           dd($check);
    //           if($check == true){
    //              if($new_password==$confirm_password){
    //                  $profile->password =  Hash::make($request->new_password);
    //              }else{
    //
    //                  return redirect('admin/view-user-edit/'.$request->hdnPKey)->with('status', 'New password and confirm password dose not match!');
    //
    //              }
    //           }
    //           else{
    //                   return redirect('admin/view-user-edit/'.$request->hdnPKey)->with('status', 'Password dose not match!');
    //
    //               }
    //
    //        }
    //        $profile->title = $request->cmbTitle;
    //        $profile->first_name = $request->first_name;
    //        $profile->last_name = $request->last_name;
    //        $profile->gender = $request->rdoGender;
    //        $profile->account_type = $request->cmbAccountType;
    //        $profile->business_name = $request->txtCompanyName;
    //        $profile->fixed_phone = $request->txtFixedPhoneNo;
    //        $profile->mobile_no = $request->txtMobilePhone;
    //        $profile->address_line1 = $request->txtAddress;
    //        $profile->address_line2 = $request->txtAddress2;
    //        $profile->address_line3 = $request->txtAddress3;
    //        $profile->post_code = $request->txtCity;
    //        $profile->area = $request->txtPostalCode;
    //        $profile->country = $request->txtCountry;
    ////        $profile->profile_photo_path="profile-photos/".$profileImage;
    //        $profile->update();
    //        LogActivityHelper::addToLog('Profile Edit');
    //
    //        return redirect('admin/view-user-edit/'.$request->hdnPKey)->with('status', 'Account Successfully Updated!');
    ////    }else{
    ////return back()->withErrors(['email'=>'Email already exists!']);
    //
    //
    //    }


    public function updateUserAccount(Request $request)
    {
        // dd(  $request->file('main_image'));
        $profile = Auth::user();

        $existingUser = User::where('email', $request->email)->where('id', '!=', $profile->id)->first();

        if ($existingUser) {
            // Redirect back with an error message if the email is already taken
            return redirect()->back()->with('error', 'The email address is already taken.');
        }

        $picture = $request->file('main_image');
        if ($picture) {
            $profile->profile_photo_path = $this->imageUploader($picture, 'main');
        }
        $new_password = $request->new_password;
        $confirm_password = $request->confirm_password;


        if ($new_password && $confirm_password) {

            if ($new_password == $confirm_password) {

                $profile->password =  Hash::make($request->new_password);
            } else {

                return redirect('view-user-edit/' . $request->hdnPKey)->with('status', 'New password and confirm password dose not match!');
            }
        }

        $profile->title = $request->cmbTitle;
        $profile->first_name = $request->first_name;
        $profile->last_name = $request->last_name;
        $profile->email = $request->email;
        $profile->gender = $request->rdoGender;
        $profile->account_type = $request->cmbAccountType;
        $profile->business_name = $request->txtCompanyName;
        $profile->fixed_phone = $request->txtFixedPhoneNo;
        $profile->mobile_no = $request->txtMobilePhone;
        $profile->address_line1 = $request->txtAddress;
        $profile->address_line2 = $request->txtAddress2;
        $profile->address_line3 = $request->txtAddress3;
        $profile->area = $request->txtCity;
        $profile->post_code = $request->txtPostalCode;
        $profile->country = $request->txtCountry;
        //        $profile->profile_photo_path="profile-photos/".$profileImage;
        // Check if account type is 1 (Personal Account) and set business_name to null
        if ($request->cmbAccountType == 1) {
            $profile->business_name = null;
        } else {
            $profile->business_name = $request->txtCompanyName;
        }

        $profile->update();
        LogActivityHelper::addToLog('Profile Edit');

        return redirect('view-user-profile')->with('status', 'Account Successfully Updated!');
        //    }else{
        //return back()->withErrors(['email'=>'Email already exists!']);


    }


    public function updateUserAccountAdmin(Request $request)
    {
        // dd(  $request->file('main_image'));
        $profile = User::where(['id' => $request->hdnPKey])->first();

        $existingUser = User::where('email', $request->email)->where('id', '!=', $profile->id)->first();

        if ($existingUser) {
            // Redirect back with an error message if the email is already taken
            return redirect()->back()->with('error', 'The email address is already taken.');
        }

        $picture = $request->file('main_image');
        if ($picture) {
            $profile->profile_photo_path = $this->imageUploader($picture, 'main');
        }
        $new_password = $request->new_password;
        $confirm_password = $request->confirm_password;


        if ($new_password && $confirm_password) {

            if ($new_password == $confirm_password) {

                $profile->password =  Hash::make($request->new_password);
            } else {

                return redirect('customer-management/view-user-edit-admin/' . $request->hdnPKey)->with('status', 'New password and confirm password dose not match!');
            }
        }

        $profile->title = $request->cmbTitle;
        $profile->first_name = $request->first_name;
        $profile->last_name = $request->last_name;
        $profile->email = $request->email;
        $profile->gender = $request->rdoGender;
        $profile->account_type = $request->cmbAccountType;
        $profile->business_name = $request->txtCompanyName;
        $profile->fixed_phone = $request->txtFixedPhoneNo;
        $profile->mobile_no = $request->txtMobilePhone;
        $profile->address_line1 = $request->txtAddress;
        $profile->address_line2 = $request->txtAddress2;
        $profile->address_line3 = $request->txtAddress3;
        $profile->area = $request->txtCity;
        $profile->post_code = $request->txtPostalCode;
        $profile->country = $request->txtCountry;
        //        $profile->profile_photo_path="profile-photos/".$profileImage;
        // Check if account type is 1 (Personal Account) and set business_name to null
        if ($request->cmbAccountType == 1) {
            $profile->business_name = null;
        } else {
            $profile->business_name = $request->txtCompanyName;
        }

        $profile->update();
        LogActivityHelper::addToLog('Profile Edit');

        return redirect('/customer-management/manage-all-customers')->with('status', 'Account Successfully Updated!');
        //    }else{
        //return back()->withErrors(['email'=>'Email already exists!']);


    }




    public function imageUploader($picture, $slug)
    {
        $fileName = Storage::disk('public_uploads')->putFile('public', $picture);
        return basename($fileName);
    }


    public function deleteUserAccount(Request $request)
    {
        if (isset($request->hdnPKey)) {
            $profile = User::where(['id' => $request->hdnPKey])->first();
            $profile->record_status = 2;
            $profile->update();

            return json_encode([
                "status" => '200',
                "message" => "Account Successfully Deleted"
            ]);
        }
    }


    public function addUserAccount(Request $input)
    {

        Validator::make($input->all(), [
            'cmbTitle' => ['required'],
            'txtFname' => ['required', 'string', 'max:30'],
            'txtLname' => ['required', 'string', 'max:30'],
            'rdoGender' => ['required'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'cmbAccountType' => ['required'],
            'txtFixedPhoneNo' => ['required', 'max:30'],
            'txtPostalCode' => ['required', 'max:255'],
            'txtAddress' => ['required', 'max:255'],
            'txtCity' => ['required', 'max:30'],
            'txtCountry' => ['required', 'max:50'],
            'txtRndPassword' => ['required', 'max:50'],

        ])->validate();

        if (!User::where('email', '=', $input->email)->exists()) {
            if (!User::where('customer_refno', '=', 'C' . substr(strval(time()), -3))->exists()) {
                $customer_refno = substr(strval(time()), -3);
            } else {
                $random_timestamp = mt_rand(strtotime('1920-01-01'), strtotime('1980-12-31'));
                $customer_refno = substr(strval($random_timestamp), -3);
            }
            $user = User::create([
                'name' => $input['txtFname'] . ' ' . $input['txtLname'],
                'email' => $input['email'],
                'password' => Hash::make($input['txtRndPassword']),
                //                'password' =>$input['txtRndPassword'],
                'customer_refno' => $customer_refno,
                'user_type' => "customer",
                'title' => $input['cmbTitle'],
                'first_name' => $input['txtFname'],
                'last_name' => $input['txtLname'],
                'account_type' => $input['cmbAccountType'],
                'business_name' => $input['txtCompanyName'],
                'gender' => $input['rdoGender'],
                'fixed_phone' => $input['txtFixedPhoneNo'],
                'post_code' => $input['txtPostalCode'],
                'address_line1' => $input['txtAddress'],
                'address_line2' => $input['txtAddress2'],
                'address_line3' => $input['txtAddress3'],
                'area' => $input['txtCity'],
                'country' => $input['txtCountry'],
                'record_status' => 1,
                'version' => 1,
                'is_active' => 1,
                'is_employee' => 0,
                'created_by' => $customer_refno,
                'email_verified_at' => date('Y-m-d h:m:s'),

            ]);

            $user_id = $user->id;
            $customer_refno = 'C' . $customer_refno  . $user_id;

            $user->update(['customer_refno' => $customer_refno]);

            LogActivityHelper::addToLog('create new user');
            return redirect('customer-management/add-customer')->with('status', 'New Customer Successfully Added!');
        } else {
            return back()->withErrors(['email' => 'Email already exists!']);
        }
    }


    public function adminManageUser($userId)
    {

        try {
            $user = User::find($userId);
            $userEmail = $user->email;

            $wallet = CustomerWallet::where('user_id', $userId)->first();
            $outstandingBalance = User::where('id', $userId)->first();

            $customerInvoicesCount = CustomerInvoices::where('customer_id', $userId)->count();
            $customerSubscriptoinsCount = SubscriptionNew::where('email', $userEmail)->count();

            $customerInvoices = CustomerInvoices::where('customer_id', $userId)->get();

            $totalUnpaidInvoiceAmount = CustomerInvoices::where('customer_id', $userId)
                ->where('pay_status', 'Not Paid')
                ->sum('total');

            $partiallyPaidInvoices = CustomerInvoices::where('customer_id', $userId)
                ->where('pay_status', 'Partially Paid')
                ->get(['total', 'paid_amount']);

            $totalUnpaidPartialInvoices = $partiallyPaidInvoices->sum(function ($invoice) {
                return ($invoice->total) - $invoice->paid_amount;
            });


            $totalUnpaidInvoiceAmount = ($totalUnpaidInvoiceAmount) + $totalUnpaidPartialInvoices;

            $totalUnpaidInvoiceAmountFinal = $totalUnpaidInvoiceAmount;
            $customerTokens = BarclaycardCustomerPaymentTokens::where('customer_id', $userId)->get(['card_mask', 'expiration_date', 'brand', 'customer_number', 'is_primary', 'id', 'email']);

            $monthlyTotals = [];
            $latestPaymentReminder = OutstandingPaymentReminderMails::where('customer_id', $userId)
                ->latest('created_at')
                ->first();
            foreach ($customerInvoices as $invoice) {
                $month = $invoice->created_at->format('F');
                $year = $invoice->created_at->format('Y');
                $payStatus = $invoice->pay_status; // Added line to get pay_status

                $total = $invoice->total;

                // If the entry for the month doesn't exist, create it
                if (!isset($monthlyTotals[$year][$month])) {
                    $monthlyTotals[$year][$month] = [
                        'total' => 0,
                        'count' => 0,
                        'totalPaid' => 0, // Added line for totalPaid
                        'totalUnpaid' => 0, // Added line for totalPaid

                    ];
                }

                // Add the total and increment the count for the corresponding month and year
                $monthlyTotals[$year][$month]['total'] += $total;
                $monthlyTotals[$year][$month]['count']++;

                if ($payStatus === 'Active') {
                    $monthlyTotals[$year][$month]['totalPaid'] += $total;
                }

                if ($payStatus === 'Not Paid') {
                    $monthlyTotals[$year][$month]['totalUnpaid'] += $total;
                }
            }

            $tableData = [];

            foreach ($monthlyTotals as $year => $months) {
                foreach ($months as $month => $data) {
                    $tableData[] = [
                        'year' => $year,
                        'month' => $month,
                        'productsSold' => $data['count'],
                        'total' => $data['total'], // Assuming the total is in cents
                        'totalPaid' => $data['totalPaid'],
                        'totalUnpaid' => $data['totalUnpaid'],
                    ];
                }
            }

            if ($outstandingBalance) {
                $outstandingBalanceAmount = $outstandingBalance->outstanding_balance;
            } else {
                $outstandingBalanceAmount = 0;
            }

            if ($wallet) {
                $walletAmount = $wallet->amount;
            } else {
                $walletAmount = 0;
            }

            if (!$user) {
                return redirect()->route('dashboard/dashboard')->with('error', 'User not found.');
            }

            $subscriptionPackages = SubscriptionPackages::all();

            $data = [
                'user' => $user,
                'walletAmount' => $walletAmount,
                'outstandingBalanceAmount' => $outstandingBalanceAmount,
                'customerInvoicesCount' => $customerInvoicesCount,
                'customerSubscriptoinsCount' => $customerSubscriptoinsCount,
                'totalUnpaidInvoiceAmountFinal' => $totalUnpaidInvoiceAmountFinal,
                'customerTokens' => $customerTokens,
                'tableData' => $tableData,
                'latestPaymentReminder' => $latestPaymentReminder,
                'subscriptionPackages' => $subscriptionPackages,



                // Add any other necessary data to pass to the view...
            ];

            return view('manageuser.useroverview', $data);
        } catch (\Exception $ex) {

            Log::error("AdminManageUserController (adminManageUser) : " . $ex);
            dd($ex);
            //return redirect()->route('dashboard/dashboard')->with('error', 'An error occurred.');
        }
    }

    public function manageUserInvoicesNormalView($userId)
    {
        try {

            $usersQuery = CustomerInvoices::where('customer_id', $userId)
                ->orderByDesc('invoice_date')
                ->get();

            $perPage = 100;

            $users = $usersQuery;

            $walletAmount = 0;
            $subscriptionPackages = SubscriptionPackages::all();
            foreach ($users as $key => $v) {
                $customer_id = $v['customer_id'];
                $wallet = CustomerWallet::where('user_id', $customer_id)->first();

                if ($wallet) {
                    $users[$key]['walletAmount'] = $wallet->amount;
                } else {
                    $users[$key]['walletAmount'] = 0;
                }
                $primaryToken = BarclaycardCustomerPaymentTokens::where('customer_id', $customer_id)
                    ->where('is_primary', 1)
                    ->first();



                if ($primaryToken) {
                    $users[$key]['primaryToken'] = $primaryToken;
                } else {
                    $users[$key]['primaryToken'] = null;
                }
            }

            $invoiceIds = $users->pluck('invoice_number')->toArray();



            $relatedEntries = OutstandingTrans::whereIn('paid_refno', $invoiceIds)->get();

            $organizedEntries = $relatedEntries->groupBy('paid_refno');

            $user = User::find($userId);

            $userInvoices = CustomerInvoices::where('customer_id', $userId)->get();

            $userEmail = $user->email;

            $wallet = CustomerWallet::where('user_id', $userId)->first();
            $outstandingBalance = User::where('id', $userId)->first();

            $customerInvoicesCount = CustomerInvoices::where('customer_id', $userId)->count();
            $customerSubscriptoinsCount = SubscriptionNew::where('email', $userEmail)->count();


            $totalUnpaidInvoiceAmount = CustomerInvoices::where('customer_id', $userId)
                ->where('pay_status', 'Not Paid')
                ->sum('total');

            $partiallyPaidInvoices = CustomerInvoices::where('customer_id', $userId)
                ->where('pay_status', 'Partially Paid')
                ->get(['total', 'paid_amount']);

            $totalUnpaidPartialInvoices = $partiallyPaidInvoices->sum(function ($invoice) {
                return ($invoice->total) - $invoice->paid_amount;
            });


            $totalUnpaidInvoiceAmount = ($totalUnpaidInvoiceAmount) + $totalUnpaidPartialInvoices;

            $totalUnpaidInvoiceAmountFinal = $totalUnpaidInvoiceAmount;
            $customerTokens = BarclaycardCustomerPaymentTokens::where('customer_id', $userId)->get(['card_mask', 'expiration_date', 'brand', 'customer_number', 'is_primary', 'id', 'email']);

            if ($outstandingBalance) {
                $outstandingBalanceAmount = $outstandingBalance->outstanding_balance;
            } else {
                $outstandingBalanceAmount = 0;
            }

            if ($wallet) {
                $walletAmount = $wallet->amount;
            } else {
                $walletAmount = 0;
            }

            $latestPaymentReminder = OutstandingPaymentReminderMails::where('customer_id', $userId)
                ->latest('created_at')
                ->first();
            $paymentOptions = PaymentOptions::all();
            $subscriptionPackages = SubscriptionPackages::all();
            $data['organizedEntries'] = $organizedEntries;
            $data['paymentOptions'] = $paymentOptions;
            $data['subscriptionPackages'] = $subscriptionPackages;

            $data['users'] = $users;
            $data['perPage'] = $perPage;
            $data['userInvoices'] = $userInvoices;
            $data['user'] = $user;
            $data['walletAmount'] = $walletAmount;
            $data['outstandingBalanceAmount'] = $outstandingBalanceAmount;
            $data['customerInvoicesCount'] = $customerInvoicesCount;
            $data['customerSubscriptoinsCount'] = $customerSubscriptoinsCount;
            $data['totalUnpaidInvoiceAmount'] = $totalUnpaidInvoiceAmount;
            $data['outstandingBalanceAmount'] = $outstandingBalanceAmount;
            $data['totalUnpaidInvoiceAmountFinal'] = $totalUnpaidInvoiceAmountFinal;
            $data['customerTokens'] = $customerTokens; // Pass $perPage to the view
            $data['latestPaymentReminder'] = $latestPaymentReminder; // Pass $perPage to the view


            return view('manageuser.manage-user-invoices-normal', $data);
        } catch (\Exception $ex) {

            Log::error("AdminManageUserController (adminManageUser) : " . $ex);
            dd($ex);
            //return redirect()->route('dashboard/dashboard')->with('error', 'An error occurred.');
        }
    }



    public function manageUserRecurringTemplates($userId)
    {
        try {

            $usersQuery = CustomerRecurringTemplates::where('customer_id', $userId)
                ->orderByDesc('invoice_date')
                ->get();

            $perPage = 100;

            $users = $usersQuery;

            $walletAmount = 0;
            $subscriptionPackages = SubscriptionPackages::all();
            foreach ($users as $key => $v) {
                $customer_id = $v['customer_id'];
                $wallet = CustomerWallet::where('user_id', $customer_id)->first();

                if ($wallet) {
                    $users[$key]['walletAmount'] = $wallet->amount;
                } else {
                    $users[$key]['walletAmount'] = 0;
                }
                $primaryToken = BarclaycardCustomerPaymentTokens::where('customer_id', $customer_id)
                    ->where('is_primary', 1)
                    ->first();



                if ($primaryToken) {
                    $users[$key]['primaryToken'] = $primaryToken;
                } else {
                    $users[$key]['primaryToken'] = null;
                }
            }

            $invoiceIds = $users->pluck('invoice_number')->toArray();


            $relatedEntries = OutstandingTrans::whereIn('paid_refno', $invoiceIds)->get();

            $organizedEntries = $relatedEntries->groupBy('paid_refno');

            $user = User::find($userId);

            $userInvoices = CustomerInvoices::where('customer_id', $userId)->get();

            $userEmail = $user->email;

            $wallet = CustomerWallet::where('user_id', $userId)->first();
            $outstandingBalance = User::where('id', $userId)->first();

            $customerInvoicesCount = CustomerInvoices::where('customer_id', $userId)->count();
            $customerSubscriptoinsCount = SubscriptionNew::where('email', $userEmail)->count();


            $totalUnpaidInvoiceAmount = CustomerInvoices::where('customer_id', $userId)
                ->where('pay_status', 'Not Paid')
                ->sum('total');

            $partiallyPaidInvoices = CustomerInvoices::where('customer_id', $userId)
                ->where('pay_status', 'Partially Paid')
                ->get(['total', 'paid_amount']);

            $totalUnpaidPartialInvoices = $partiallyPaidInvoices->sum(function ($invoice) {
                return ($invoice->total) - $invoice->paid_amount;
            });


            $totalUnpaidInvoiceAmount = ($totalUnpaidInvoiceAmount) + $totalUnpaidPartialInvoices;

            $totalUnpaidInvoiceAmountFinal = $totalUnpaidInvoiceAmount;
            $customerTokens = BarclaycardCustomerPaymentTokens::where('customer_id', $userId)->get(['card_mask', 'expiration_date', 'brand', 'customer_number', 'is_primary', 'id', 'email']);

            if ($outstandingBalance) {
                $outstandingBalanceAmount = $outstandingBalance->outstanding_balance;
            } else {
                $outstandingBalanceAmount = 0;
            }

            if ($wallet) {
                $walletAmount = $wallet->amount;
            } else {
                $walletAmount = 0;
            }

            $latestPaymentReminder = OutstandingPaymentReminderMails::where('customer_id', $userId)
                ->latest('created_at')
                ->first();
            $paymentOptions = PaymentOptions::all();
            $subscriptionPackages = SubscriptionPackages::all();
            $data['organizedEntries'] = $organizedEntries;
            $data['paymentOptions'] = $paymentOptions;
            $data['subscriptionPackages'] = $subscriptionPackages;

            $data['users'] = $users;
            $data['perPage'] = $perPage;
            $data['userInvoices'] = $userInvoices;
            $data['user'] = $user;
            $data['walletAmount'] = $walletAmount;
            $data['outstandingBalanceAmount'] = $outstandingBalanceAmount;
            $data['customerInvoicesCount'] = $customerInvoicesCount;
            $data['customerSubscriptoinsCount'] = $customerSubscriptoinsCount;
            $data['totalUnpaidInvoiceAmount'] = $totalUnpaidInvoiceAmount;
            $data['outstandingBalanceAmount'] = $outstandingBalanceAmount;
            $data['totalUnpaidInvoiceAmountFinal'] = $totalUnpaidInvoiceAmountFinal;
            $data['customerTokens'] = $customerTokens;
            $data['latestPaymentReminder'] = $latestPaymentReminder;


            return view('manageuser.manage-user-recurring-templates', $data);
        } catch (\Exception $ex) {

            Log::error("AdminManageUserController (adminManageUser) : " . $ex);
            dd($ex);
            //return redirect()->route('dashboard/dashboard')->with('error', 'An error occurred.');
        }
    }


    public function manageUserInvoicesView($userId)
    {

        try {
            $user = User::find($userId);

            $userInvoices = CustomerInvoices::where('customer_id', $userId)->get();

            $userEmail = $user->email;

            $wallet = CustomerWallet::where('user_id', $userId)->first();
            $outstandingBalance = User::where('id', $userId)->first();

            $customerInvoicesCount = CustomerInvoices::where('customer_id', $userId)->count();
            $customerSubscriptoinsCount = SubscriptionNew::where('email', $userEmail)->count();

            $subscriptionPackages = SubscriptionPackages::all();
            $totalUnpaidInvoiceAmount = CustomerInvoices::where('customer_id', $userId)
                ->where('pay_status', 'Not Paid')
                ->sum('total');

            $partiallyPaidInvoices = CustomerInvoices::where('customer_id', $userId)
                ->where('pay_status', 'Partially Paid')
                ->get(['total', 'paid_amount']);

            $totalUnpaidPartialInvoices = $partiallyPaidInvoices->sum(function ($invoice) {
                return ($invoice->total) - $invoice->paid_amount;
            });


            $totalUnpaidInvoiceAmount = ($totalUnpaidInvoiceAmount) + $totalUnpaidPartialInvoices;

            $totalUnpaidInvoiceAmountFinal = $totalUnpaidInvoiceAmount;
            $customerTokens = BarclaycardCustomerPaymentTokens::where('customer_id', $userId)->get(['card_mask', 'expiration_date', 'brand', 'customer_number', 'is_primary', 'id', 'email']);

            if ($outstandingBalance) {
                $outstandingBalanceAmount = $outstandingBalance->outstanding_balance;
            } else {
                $outstandingBalanceAmount = 0;
            }

            if ($wallet) {
                $walletAmount = $wallet->amount;
            } else {
                $walletAmount = 0;
            }

            $latestPaymentReminder = OutstandingPaymentReminderMails::where('customer_id', $userId)
                ->latest('created_at')
                ->first();
            return view('manageuser.manage-user-invoices', [
                'userInvoices' => $userInvoices,
                'user' => $user,
                'walletAmount' => $walletAmount,
                'outstandingBalanceAmount' => $outstandingBalanceAmount,
                'customerInvoicesCount' => $customerInvoicesCount,
                'customerSubscriptoinsCount' => $customerSubscriptoinsCount,
                'totalUnpaidInvoiceAmount' => $totalUnpaidInvoiceAmount,
                'totalUnpaidInvoiceAmountFinal' => $totalUnpaidInvoiceAmountFinal,
                'customerTokens' => $customerTokens,
                'latestPaymentReminder' => $latestPaymentReminder,
                'subscriptionPackages' => $subscriptionPackages,
            ]);
        } catch (\Exception $ex) {

            Log::error("AdminManageUserController (adminManageUser) : " . $ex);
            dd($ex);
            //return redirect()->route('dashboard/dashboard')->with('error', 'An error occurred.');
        }
    }

    public function manageUserLogView($userId)
    {

        try {
            $user = User::find($userId);

            $userInvoices = CustomerInvoices::where('customer_id', $userId)->get();

            $userEmail = $user->email;

            $wallet = CustomerWallet::where('user_id', $userId)->first();
            $outstandingBalance = User::where('id', $userId)->first();
            $subscriptionPackages = SubscriptionPackages::all();
            $customerInvoicesCount = CustomerInvoices::where('customer_id', $userId)->count();
            $customerSubscriptoinsCount = SubscriptionNew::where('email', $userEmail)->count();

            $totalUnpaidInvoiceAmount = CustomerInvoices::where('customer_id', $userId)
                ->where('pay_status', 'Not Paid')
                ->sum('total');

            $partiallyPaidInvoices = CustomerInvoices::where('customer_id', $userId)
                ->where('pay_status', 'Partially Paid')
                ->get(['total', 'paid_amount']);

            $totalUnpaidPartialInvoices = $partiallyPaidInvoices->sum(function ($invoice) {
                return ($invoice->total) - $invoice->paid_amount;
            });


            $totalUnpaidInvoiceAmount = ($totalUnpaidInvoiceAmount) + $totalUnpaidPartialInvoices;

            $totalUnpaidInvoiceAmountFinal = $totalUnpaidInvoiceAmount;
            $customerTokens = BarclaycardCustomerPaymentTokens::where('customer_id', $userId)->get(['card_mask', 'expiration_date', 'brand', 'customer_number', 'is_primary', 'id', 'email']);


            if ($outstandingBalance) {
                $outstandingBalanceAmount = $outstandingBalance->outstanding_balance;
            } else {
                $outstandingBalanceAmount = 0;
            }

            if ($wallet) {
                $walletAmount = $wallet->amount;
            } else {
                $walletAmount = 0;
            }




            $userActivityLogs = LogActivity::where('user_id', $userId)->get(['subject', 'url', 'method', 'ip', 'agent', 'created_at']);

            $userInvoices = CustomerInvoices::where('customer_id', $userId)->get();

            $latestPaymentReminder = OutstandingPaymentReminderMails::where('customer_id', $userId)
                ->latest('created_at')
                ->first();
            return view('manageuser.manage-user-log-view', [
                'userInvoices' => $userInvoices,
                'user' => $user,
                'walletAmount' => $walletAmount,
                'outstandingBalanceAmount' => $outstandingBalanceAmount,
                'customerInvoicesCount' => $customerInvoicesCount,
                'customerSubscriptoinsCount' => $customerSubscriptoinsCount,
                'totalUnpaidInvoiceAmountFinal' => $totalUnpaidInvoiceAmountFinal,
                'customerTokens' => $customerTokens,
                'userActivityLogs' => $userActivityLogs,
                'latestPaymentReminder' => $latestPaymentReminder,
                'subscriptionPackages' => $subscriptionPackages,

            ]);
        } catch (\Exception $ex) {

            Log::error("AdminManageUserController (adminManageUser) : " . $ex);
            dd($ex);
            //return redirect()->route('dashboard/dashboard')->with('error', 'An error occurred.');
        }
    }

    public function manageUserSubscriptionView($userId)
    {

        try {
            $user = User::find($userId);

            $userInvoices = CustomerInvoices::where('customer_id', $userId)->get();
            $subscriptionPackages = SubscriptionPackages::all();
            $userEmail = $user->email;

            $wallet = CustomerWallet::where('user_id', $userId)->first();
            $outstandingBalance = User::where('id', $userId)->first();

            $customerInvoicesCount = CustomerInvoices::where('customer_id', $userId)->count();
            $customerSubscriptoinsCount = SubscriptionNew::where('email', $userEmail)->count();

            $userSubscriptions = SubscriptionNew::where('email', $user->email)->orderByDesc('created_at')->get();

            $totalUnpaidInvoiceAmount = CustomerInvoices::where('customer_id', $userId)
                ->where('pay_status', 'Not Paid')
                ->sum('total');

            $partiallyPaidInvoices = CustomerInvoices::where('customer_id', $userId)
                ->where('pay_status', 'Partially Paid')
                ->get(['total', 'paid_amount']);

            $totalUnpaidPartialInvoices = $partiallyPaidInvoices->sum(function ($invoice) {
                return ($invoice->total) - $invoice->paid_amount;
            });

            $totalUnpaidInvoiceAmount = ($totalUnpaidInvoiceAmount) + $totalUnpaidPartialInvoices;

            $totalUnpaidInvoiceAmountFinal = $totalUnpaidInvoiceAmount;
            $customerTokens = BarclaycardCustomerPaymentTokens::where('customer_id', $userId)->get(['card_mask', 'expiration_date', 'brand', 'customer_number', 'is_primary', 'id', 'email']);


            if ($outstandingBalance) {
                $outstandingBalanceAmount = $outstandingBalance->outstanding_balance;
            } else {
                $outstandingBalanceAmount = 0;
            }

            if ($wallet) {
                $walletAmount = $wallet->amount;
            } else {
                $walletAmount = 0;
            }
            $latestPaymentReminder = OutstandingPaymentReminderMails::where('customer_id', $userId)
                ->latest('created_at')
                ->first();

            return view('manageuser.manage-user-subscription-view', [
                'userInvoices' => $userInvoices,
                'user' => $user,
                'walletAmount' => $walletAmount,
                'outstandingBalanceAmount' => $outstandingBalanceAmount,
                'customerInvoicesCount' => $customerInvoicesCount,
                'customerSubscriptoinsCount' => $customerSubscriptoinsCount,
                'totalUnpaidInvoiceAmountFinal' => $totalUnpaidInvoiceAmountFinal,
                'customerTokens' => $customerTokens,
                'subscriptionPayments' => $userSubscriptions,
                'latestPaymentReminder' => $latestPaymentReminder,
                'subscriptionPackages' => $subscriptionPackages,
            ]);
        } catch (\Exception $ex) {

            Log::error("AdminManageUserController (adminManageUser) : " . $ex);
            dd($ex);
            //return redirect()->route('dashboard/dashboard')->with('error', 'An error occurred.');
        }
    }

    public function manageUserPaymentView($userId)
    {

        try {
            $user = User::find($userId);

            $userInvoices = CustomerInvoices::where('customer_id', $userId)->get();


            $userEmail = $user->email;

            $wallet = CustomerWallet::where('user_id', $userId)->first();
            $outstandingBalance = User::where('id', $userId)->first();
            $subscriptionPackages = SubscriptionPackages::all();
            $customerInvoicesCount = CustomerInvoices::where('customer_id', $userId)->count();
            $customerSubscriptoinsCount = SubscriptionNew::where('email', $userEmail)->count();

            $totalUnpaidInvoiceAmount = CustomerInvoices::where('customer_id', $userId)
                ->where('pay_status', 'Not Paid')
                ->sum('total');

            $partiallyPaidInvoices = CustomerInvoices::where('customer_id', $userId)
                ->where('pay_status', 'Partially Paid')
                ->get(['total', 'paid_amount']);

            $totalUnpaidPartialInvoices = $partiallyPaidInvoices->sum(function ($invoice) {
                return ($invoice->total) - $invoice->paid_amount;
            });


            $totalUnpaidInvoiceAmount = ($totalUnpaidInvoiceAmount) + $totalUnpaidPartialInvoices;

            $totalUnpaidInvoiceAmountFinal = $totalUnpaidInvoiceAmount;


            $customerTokens = BarclaycardCustomerPaymentTokens::where('customer_id', $userId)->get(['card_mask', 'expiration_date', 'brand', 'customer_number', 'is_primary', 'id', 'email']);
            $latestPaymentReminder = OutstandingPaymentReminderMails::where('customer_id', $userId)
                ->latest('created_at')
                ->first();
            $userOutstandingTrans = OutstandingTrans::where(function ($query) use ($user) {
                $query->where('customer_id', $user->id)
                    ->orWhere(function ($query) use ($user) {
                        $query->whereNull('customer_id')
                            ->where('email', $user->email);
                    });
            })->orderBy('paid_date', 'desc')->get();


            if ($outstandingBalance) {
                $outstandingBalanceAmount = $outstandingBalance->outstanding_balance;
            } else {
                $outstandingBalanceAmount = 0;
            }

            if ($wallet) {
                $walletAmount = $wallet->amount;
            } else {
                $walletAmount = 0;
            }


            $primaryToken = BarclaycardCustomerPaymentTokens::where('customer_id', $userId)
                ->where('is_primary', 1)
                ->first();


            $UnpaiduserInvoices = CustomerInvoices::where('customer_id', $userId)
                ->whereIn('pay_status', ['Not Paid', 'Partially Paid'])
                ->get();



            $invoiceIds = $UnpaiduserInvoices->pluck('id')->implode('-');


            return view('manageuser.manage-user-payment-view', [
                'userInvoices' => $userInvoices,
                'user' => $user,
                'walletAmount' => $walletAmount,
                'outstandingBalanceAmount' => $outstandingBalanceAmount,
                'customerInvoicesCount' => $customerInvoicesCount,
                'customerSubscriptoinsCount' => $customerSubscriptoinsCount,
                'totalUnpaidInvoiceAmountFinal' => $totalUnpaidInvoiceAmountFinal,
                'customerTokens' => $customerTokens,
                'userOutstandingTrans' => $userOutstandingTrans,
                'latestPaymentReminder' => $latestPaymentReminder,
                'invoiceIds' => $invoiceIds,
                'primaryToken' => $primaryToken,
                'subscriptionPackages' => $subscriptionPackages,
            ]);
        } catch (\Exception $ex) {

            Log::error("AdminManageUserController (adminManageUser) : " . $ex);
            dd($ex);
            //return redirect()->route('dashboard/dashboard')->with('error', 'An error occurred.');
        }
    }


    // Inside your UserManagementController

    // Inside your UserManagementController

    public function bulkInvoiceUpdatePaymentType(Request $request)
    {
        $selectedInvoiceIds = json_decode($request->input('selected_invoices'));
        $paidAmounts = $request->input('paid_amount');
        $paymentMethods = $request->input('payment_method');
        $paidDates = $request->input('paid_date');
        $referenceNumbers = $request->input('reference_number');

        $successCount = 0; // Counter for successful updates

        foreach ($selectedInvoiceIds as $key => $invoiceId) {
            $invoice = CustomerInvoices::findOrFail($invoiceId);
            $paidAmount = str_replace(',', '', $paidAmounts[$key]); // Remove commas from the string
            $paidAmount = floatval($paidAmount); // Convert to float to retain decimal values
            $paymentMethod = $paymentMethods[0];
            $paidDate = $paidDates[0];
            $referenceNumber = $referenceNumbers[0];

            $invoice->paid_amount += $paidAmount;

            if (!empty($paymentMethod)) {
                $invoice->inv_type = $paymentMethod;
            }

            if (!empty($paidDate)) {
                $invoice->paid_date = $paidDate;
            }

            if (!empty($referenceNumber)) {
                if (($invoice->inv_type == 5 || $invoice->inv_type == 7) && !empty($referenceNumber)) {
                    $invoice->epdq_refno = $referenceNumber;
                } elseif ($invoice->inv_type == 4 && !empty($referenceNumber)) {
                    $invoice->bank_refno = $referenceNumber;
                } elseif ($invoice->inv_type == 8 && !empty($referenceNumber)) {
                    $invoice->cardless_description = $referenceNumber;
                }
            }

            if ($invoice->paid_amount >= $invoice->total) {
                $invoice->pay_status = 'Active';
            } else {
                $invoice->pay_status = 'Partially Paid';
            }

            if ($invoice->save()) {
                $successCount++;

                if ($paymentMethod == 6) {
                    $customerId = $invoice->customer_id;
                    $customerWallet = CustomerWallet::where('user_id', $customerId)->first();

                    if ($customerWallet) {
                        $invoiceTotal = $invoice->total; // Assuming this represents the invoice total (divide by 100)
                        $customerWallet->amount -= $invoiceTotal;
                        $customerWallet->save();
                    }
                }
            }
        }

        if ($successCount === count($selectedInvoiceIds)) {
            return redirect()->back()->with('success', 'All Selected Invoices paid successfully.');
        } else {
            return redirect()->back()->with('error', 'Some Selected Invoices failed to update.');
        }
    }


    public function manageAllUsers()
    {
        // Retrieve users where 'is_active' is 1, ordered by creation time, and paginate by 25 per page
        $users = User::where('is_active', 1)
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        return view('manageuser.view-all-manage-user', compact('users'));
    }



    public function verifyManually($userId)
    {
        try {
            $user = User::findOrFail($userId);

            $user->email_verified_at = now(); // You can use `now()` for simplicity
            $user->save();

            return redirect()->back()->with('success', 'User manually verified!');
        } catch (ModelNotFoundException $exception) {
            return redirect()->back()->with('error', 'User not found!')->withErrors($exception->getMessage());
        } catch (\Exception $exception) {
            dd($exception); // Use dd to dump and die, you can log or handle the exception as needed
        }

        // Your existing code...
    }

    public function searchUsers(Request $request)
    {
        $searchTerm = $request->input('search');
        $isActive = $request->input('is_active', 'all'); // Default is 'all'
        $timeFrame = $request->input('time_frame', 'all'); // Default is 'all'

        // Build the query with conditions based on search term
        $query = User::query();

        // Add where clauses for search
        $query->where(function ($q) use ($searchTerm) {
            $q->where('first_name', 'like', "%$searchTerm%")
                ->orWhere('last_name', 'like', "%{$searchTerm}%")
                ->orWhere('email', 'like', "%{$searchTerm}%")
                ->orWhere('business_name', 'like', "%{$searchTerm}%")
                ->orWhere('fixed_phone', 'like', "%{$searchTerm}%")
                ->orWhere('customer_refno', 'like', "%{$searchTerm}%")
                ->orWhere('address_line1', 'like', "%{$searchTerm}%")
                ->orWhere('address_line2', 'like', "%{$searchTerm}%")
                ->orWhere('address_line3', 'like', "%{$searchTerm}%")
                ->orWhere('post_code', 'like', "%{$searchTerm}%")
                ->orWhere('area', 'like', "%{$searchTerm}%");
        });

        // Filter based on active status if not 'all'
        if ($isActive !== 'all') {
            $query->where('is_active', $isActive === 'active' ? 1 : 0);
        }

        // Filter by time frame if not 'all'
        if ($timeFrame !== 'all') {
            $daysAgo = now()->subDays(intval($timeFrame));
            $query->where('created_at', '>=', $daysAgo);
        }

        $users = $query->paginate(50); // Paginate the results

        return view('manageuser.view-all-manage-user', compact('users'));
    }


    public function manageUserNetworkView($userId, $networkID)
    {
        try {
            $user = User::find($userId);

            $userInvoices = CustomerInvoices::where('customer_id', $userId)->get();

            $userEmail = $user->email;

            $wallet = CustomerWallet::where('user_id', $userId)->first();
            $outstandingBalance = User::where('id', $userId)->first();

            $customerInvoicesCount = CustomerInvoices::where('customer_id', $userId)->count();
            $customerSubscriptoinsCount = SubscriptionNew::where('email', $userEmail)->count();


            $customerInvoices = CustomerInvoices::where('customer_id', $userId)->get();

            $totalUnpaidInvoiceAmount = CustomerInvoices::where('customer_id', $userId)
                ->where('pay_status', 'Not Paid')
                ->sum('total');

            $partiallyPaidInvoices = CustomerInvoices::where('customer_id', $userId)
                ->where('pay_status', 'Partially Paid')
                ->get(['total', 'paid_amount']);

            $totalUnpaidPartialInvoices = $partiallyPaidInvoices->sum(function ($invoice) {
                return ($invoice->total) - $invoice->paid_amount;
            });


            $totalUnpaidInvoiceAmount = ($totalUnpaidInvoiceAmount) + $totalUnpaidPartialInvoices;

            $totalUnpaidInvoiceAmountFinal = $totalUnpaidInvoiceAmount;


            $switchConnection = 'switch';

            $latestPaymentReminder = OutstandingPaymentReminderMails::where('customer_id', $userId)
                ->latest('created_at')
                ->first();

            if ($outstandingBalance) {
                $outstandingBalanceAmount = $outstandingBalance->outstanding_balance;
            } else {
                $outstandingBalanceAmount = 0;
            }

            if ($wallet) {
                $walletAmount = $wallet->amount;
            } else {
                $walletAmount = 0;
            }
            $matchingNetwork = NetworkDetails::where('id', $networkID)->get()->first();
            $matchingSipPinRegenerations = SipAccountPinRegenerations::where('network_id', $networkID)->get();
            $matchingSipSipRegenerations = SipAccountSipRegenerations::where('network_id', $networkID)->get();
            $matchingSipConnectionHistory = SipConnectionHistory::where('network_id', $networkID)->get();
            $matchingPortAssignHistory = PortAssignHistory::where('network_id', $networkID)->get();




            $sipValue = $matchingNetwork->sip ?? '';

            $topupHistory = SipTopupHistory::where('sip', $sipValue)->get();

            $currentTechPrefix = DB::connection($switchConnection)->table('clientsshared')
                ->where('login', $sipValue)
                ->value('tech_prefix');

            if (str_contains($currentTechPrefix, "CP:0->+")) {
                $start = strpos($currentTechPrefix, ">", strpos($currentTechPrefix, ">", 2 - 1) + strlen(">")) + 1;
            } else {
                $start = strpos($currentTechPrefix, ">") + 1;
            }

            $currentPort = "";
            while (is_numeric($currentTechPrefix[$start])) {
                $currentPort .= $currentTechPrefix[$start];
                $start++;
            }


            $currentPort = $this->_get_existing_telephone_no($currentTechPrefix);

            $switchPortCount = DB::connection($switchConnection)
                ->table('clientsshared')
                ->where('tech_prefix', 'LIKE', '%' . $currentPort . '%')
                ->count();

            $matchingClientsDetails = DB::connection($switchConnection)
                ->table('clientsshared')
                ->where('login', $sipValue)
                ->select('id_client', 'account_state', 'tech_prefix', 'type')
                ->first();

            if ($matchingClientsDetails) {
                $idClient = $matchingClientsDetails->id_client;
                $acType = $matchingClientsDetails->type;
                $accountState = $matchingClientsDetails->account_state;
            }
            $tariffs = DB::connection($switchConnection)->table('tariffsnames')->get();


            if ($matchingNetwork) {
                $matchingClis = DB::connection($switchConnection)
                    ->table('clientscallbackphones')
                    ->where('id_client', $idClient)
                    ->get();
            }

            if ($matchingNetwork) {
                $matchingSppedDials = DB::connection($switchConnection)
                    ->table('addressbook')
                    ->where('id_client', $idClient)
                    ->get();
            }

            $allGateways = Ports::all();
            $matchingIncomingForwardingNumbers = IncomingForwardingNumbers::where('network_id', $networkID)
                ->where('is_active', 1)
                ->get();
            $customerTokens = BarclaycardCustomerPaymentTokens::where('customer_id', $userId)->get(['card_mask', 'expiration_date', 'brand', 'customer_number', 'is_primary', 'id', 'email']);

            $matchingCalls = DB::connection($switchConnection)
                ->table('calls')
                ->where('id_client', $idClient)
                ->get();

            $recurringTemplates = CustomerRecurringTemplates::all();
            $packages = SubscriptionPackages::all();

            $subscriptionPackages = SubscriptionPackages::all();
            return view('manageuser.network-details', [
                'userInvoices' => $userInvoices,
                'recurringTemplates' => $recurringTemplates,
                'packages' => $packages,
                'user' => $user,
                'walletAmount' => $walletAmount,
                'outstandingBalanceAmount' => $outstandingBalanceAmount,
                'customerInvoicesCount' => $customerInvoicesCount,
                'customerSubscriptoinsCount' => $customerSubscriptoinsCount,
                'customerTokens' => $customerTokens,
                'matchingNetwork' => $matchingNetwork,
                'matchingSipPinRegenerations' => $matchingSipPinRegenerations,
                'matchingSipSipRegenerations' => $matchingSipSipRegenerations,
                'matchingClientsDetails' => $matchingClientsDetails,
                'accountState' => $accountState,
                'idClient' => $idClient,
                'topupHistory' => $topupHistory,
                'matchingCalls' => $matchingCalls,
                'acType' => $acType,
                'matchingClis' => $matchingClis ?? NULL,
                'tariffs' => $tariffs,
                'allGateways' => $allGateways,
                'currentPort' => $currentPort,
                'switchPortCount' => $switchPortCount,
                'matchingIncomingForwardingNumbers' => $matchingIncomingForwardingNumbers,
                'totalUnpaidInvoiceAmountFinal' => $totalUnpaidInvoiceAmountFinal,
                'latestPaymentReminder' => $latestPaymentReminder,
                'matchingSipConnectionHistory' => $matchingSipConnectionHistory,
                'matchingPortAssignHistory' => $matchingPortAssignHistory,
                'subscriptionPackages' => $subscriptionPackages,
                'matchingSppedDials' => $matchingSppedDials,

            ]);
        } catch (\Exception $ex) {

            Log::error("AdminManageUserController (adminManageUser) : " . $ex);
            dd($ex);
            //return redirect()->route('dashboard/dashboard')->with('error', 'An error occurred.');
        }
    }


    public function manageAllNetworksView($userId)
    {
        try {
            $user = User::find($userId);

            $userInvoices = CustomerInvoices::where('customer_id', $userId)->get();

            $userEmail = $user->email;

            $latestPaymentReminder = OutstandingPaymentReminderMails::where('customer_id', $userId)
                ->latest('created_at')
                ->first();

            $wallet = CustomerWallet::where('user_id', $userId)->first();
            $outstandingBalance = User::where('id', $userId)->first();

            $customerInvoicesCount = CustomerInvoices::where('customer_id', $userId)->count();
            $customerSubscriptoinsCount = SubscriptionNew::where('email', $userEmail)->count();


            $customerInvoices = CustomerInvoices::where('customer_id', $userId)->get();

            $totalUnpaidInvoiceAmount = CustomerInvoices::where('customer_id', $userId)
                ->where('pay_status', 'Not Paid')
                ->sum('total');

            $partiallyPaidInvoices = CustomerInvoices::where('customer_id', $userId)
                ->where('pay_status', 'Partially Paid')
                ->get(['total', 'paid_amount']);

            $totalUnpaidPartialInvoices = $partiallyPaidInvoices->sum(function ($invoice) {
                return ($invoice->total) - $invoice->paid_amount;
            });


            $totalUnpaidInvoiceAmount = ($totalUnpaidInvoiceAmount) + $totalUnpaidPartialInvoices;

            $totalUnpaidInvoiceAmountFinal = $totalUnpaidInvoiceAmount;
            $switchConnection = 'switch';

            if ($outstandingBalance) {
                $outstandingBalanceAmount = $outstandingBalance->outstanding_balance;
            } else {
                $outstandingBalanceAmount = 0;
            }

            if ($wallet) {
                $walletAmount = $wallet->amount;
            } else {
                $walletAmount = 0;
            }
            $matchingNetwork = NetworkDetails::where('customer_id', $userId)->get()->first();
            $matchingSipPinRegenerations = SipAccountPinRegenerations::where('customer_id', $userId)->get();
            $matchingSipSipRegenerations = SipAccountSipRegenerations::where('customer_id', $userId)->get();
            $matchingNetworkDetails = NetworkDetails::where('customer_id', $userId)->get();

            $sipValue = $matchingNetwork->sip ?? '';


            $matchingClientsDetails = DB::connection($switchConnection)
                ->table('clientsshared')
                ->where('login', $sipValue)
                ->get();


            $tariffs = DB::connection($switchConnection)->table('tariffsnames')->get();


            if ($matchingNetwork) {
                $matchingClis = CliNumbers::where('network_id', $matchingNetwork->id)
                    ->where('is_active', 1)
                    ->get();
            }
            $allGateways = Gateways::all();
            $allDidSuppliers = DidSuppliers::all();
            $allDidRoutes = DidRoutes::all();
            $didList = DidList::where('is_allocated', 0)->get();
            $allDids = CustomerDids::where('customer_id', $user->id)->get();
            $subscriptionPackages = SubscriptionPackages::all();
            $customerTokens = BarclaycardCustomerPaymentTokens::where('customer_id', $userId)->get(['card_mask', 'expiration_date', 'brand', 'customer_number', 'is_primary', 'id', 'email']);
            // $users = User::all();

            return view('manageuser.all-networks', [
                'userInvoices' => $userInvoices,
                'user' => $user,
                'didList' => $didList,
                'walletAmount' => $walletAmount,
                'outstandingBalanceAmount' => $outstandingBalanceAmount,
                'customerInvoicesCount' => $customerInvoicesCount,
                'customerSubscriptoinsCount' => $customerSubscriptoinsCount,
                'customerTokens' => $customerTokens,
                'matchingNetwork' => $matchingNetwork,
                'matchingSipPinRegenerations' => $matchingSipPinRegenerations,
                'matchingClientsDetails' => $matchingClientsDetails,
                'matchingClis' => $matchingClis ?? NULL,
                'tariffs' => $tariffs,
                'allGateways' => $allGateways,
                'allDidSuppliers' => $allDidSuppliers,
                'allDids' => $allDids,
                'allDidRoutes' => $allDidRoutes,
                'matchingNetworkDetails' => $matchingNetworkDetails,
                'totalUnpaidInvoiceAmountFinal' => $totalUnpaidInvoiceAmountFinal,
                'latestPaymentReminder' => $latestPaymentReminder,
                'subscriptionPackages' => $subscriptionPackages,

            ]);
        } catch (\Exception $ex) {

            Log::error("AdminManageUserController (adminManageUser) : " . $ex);
            dd($ex);
            // return redirect()->route('dashboard/dashboard')->with('error', 'An error occurred.');
        }
    }

    public function networkRegeneratePin(Request $request)
    {
        $randomPin = rand(1000000000, 9999999999);
        $now = now();
        $sip = $request->input('sip');

        $switchConnection = 'switch';

        $existingPin = NetworkDetails::where('id', $request->input('network_id'))->first();


        $existingSipPin = DB::connection($switchConnection)->table('clientsshared')
            ->where('password', $randomPin)
            ->first();

        if ($existingPin) {

            while ($existingSipPin) {
                $randomPin = rand(1000000000, 9999999999);

                $existingPin = DB::connection($switchConnection)->table('clientsshared')
                    ->where('password', $randomPin)
                    ->first();
            }

            $matchingEntry = DB::connection($switchConnection)
                ->table('clientsshared')
                ->where('login', '=', $sip)
                ->first();

            if ($matchingEntry) {
                DB::connection($switchConnection)
                    ->table('clientsshared')
                    ->where('login', '=', $sip)
                    ->update(['password' => strval($randomPin)]);
            }

            $existingPin->update([
                'pin' => $randomPin,
                'pin_regenerate_count' => $existingPin->pin_regenerate_count + 1,
                'last_pin_regenerate' => $now,
                'pin_mail_sent' => 2,
            ]);

            SipAccountPinRegenerations::create([
                'customer_id' => $request->input('user_id'),
                'network_id' => $existingPin->id,
                'pin' => $randomPin,
                'regenerated_by' => auth()->user()->id,
            ]);

            session()->flash('success', 'PIN regeneration successful');
        } else {
            $network = NetworkDetails::create([
                'customer_id' => $request->input('user_id'),
                'customer_refno' => $request->input('customer_refno'),
                'pin' => $randomPin,
                'pin_regenerate_count' => 1,
                'last_pin_regenerate' => $now,
                'is_active' => 1,
                'status' => 1,
                'pin_mail_sent' => 0,
            ]);

            SipAccountPinRegenerations::create([
                'customer_id' => $request->input('user_id'),
                'network_id' => $network->id,
                'pin' => $network->pin,
                'regenerated_by' => auth()->user()->id,
            ]);

            session()->flash('error', 'PIN regeneration failed, something went wrong');
        }



        return redirect()->back();
    }

    public function networkRegenerateSip(Request $request)
    {
        try {
            $sipPrefix = SipPrefix::first();
            $randomSip = $sipPrefix->prefix . str_pad(rand(10000, 99999), 5, '0', STR_PAD_LEFT);
            $randomPin = rand(1000000000, 9999999999);
            $now = now();
            $selected_tariff_id = (int) $request->input('tariff_id');

            $subscription_id = $request->input('subscription_id');

            $existingPin = NetworkDetails::where('customer_id', $request->input('user_id'))->first();

            $switchConnection = 'switch';

            $existingSip = DB::connection($switchConnection)->table('clientsshared')
                ->where('login', $randomSip)
                ->first();

            $existingSipPin = DB::connection($switchConnection)->table('clientsshared')
                ->where('password', $randomPin)
                ->first();

            $selectedTariff = DB::connection($switchConnection)
                ->table('tariffsnames')
                ->where('id_tariff', $selected_tariff_id)
                ->first();

            if (!$selectedTariff) {
                return response()->json(['error' => 'Tariff not found'], 404);
            }

            $idCurrency = $selectedTariff->id_currency;



            while ($existingSip) {
                $sipPrefix = SipPrefix::first();
                $randomSip = $sipPrefix->prefix . str_pad(rand(10000, 99999), 5, '0', STR_PAD_LEFT);

                $existingSip = DB::connection($switchConnection)->table('clientsshared')
                    ->where('login', $randomSip)
                    ->first();
            }

            while ($existingSipPin) {
                $randomPin = rand(1000000000, 9999999999);

                $existingPin = DB::connection($switchConnection)->table('clientsshared')
                    ->where('password', $randomPin)
                    ->first();
            }

            try {
                DB::connection($switchConnection)->getPdo();

                DB::connection($switchConnection)->table('clientsshared')->insert([
                    'login' => $randomSip,
                    'password' => $randomPin,
                    'web_password' => 'Ta_762870%',
                    'type' => "262145",
                    'id_tariff' => $selected_tariff_id,
                    'account_state' => 0.0000,
                    'tech_prefix' => 'DP:94->7;TP:00->;',
                    'id_reseller' => -1,
                    'type2' => 2,
                    'type3' => 0,
                    'id_intrastate_tariff' => -1,
                    'id_currency' => $idCurrency,
                    'codecs' => "8388610",
                    'primary_codec' => 2,
                    'free_seconds' => 0,
                    'id_tariff_vod' => 0,
                    'id_cli_map' => 0,
                    'id_pbx_company' => NULL,
                    'video_codecs' => 1,
                    'video_primary_codec' => 1,
                    'fax_codecs' => 1,
                    'fax_primary_codec' => 1,
                    'id_dial_plan' => 1,
                    'id_dial_plan_map' => 1,
                ]);
            } catch (\Exception $e) {
                return response()->json(['error' => 'Database connection not established'], 500);
            }

            $network = NetworkDetails::create([
                'customer_id' => $request->input('user_id'),
                'customer_refno' => $request->input('customer_refno'),
                'sip' => $randomSip,
                'pin' => $randomPin,
                'sip_regenerate_count' => 1,
                'last_sip_regenerate' => $now,
                'is_active' => 1,
                'status' => 1,
                'switch_status' => 1,
                'pin_regenerate_count' => 1,
                'last_pin_regenerate' => $now,
                'package_id' => $request->input('subscription_package_id'), // Save the selected package
                'topup_amounts' => $request->input('custom_topup_amounts'),
            ]);

            SipAccountSipRegenerations::create([
                'customer_id' => $request->input('user_id'),
                'network_id' => $network->id,
                'sip' => $network->sip,
                'regenerated_by' => auth()->user()->id,
            ]);

            SipAccountPinRegenerations::create([
                'customer_id' => $request->input('user_id'),
                'network_id' => $network->id,
                'pin' => $network->pin,
                'regenerated_by' => auth()->user()->id,
            ]);

            $subscription = SubscriptionNew::find($subscription_id);

            if ($subscription && is_null($subscription->network_id)) {
                $subscription->network_id = $network->id;
                $subscription->save();
            }
            session()->flash('success', 'New SIP regeneration successful');

            return redirect()->back();
        } catch (\Exception $e) {
            dd($e);
        }
    }

    public function serverDeactivateSip(Request $request)
    {

        try {
            $sip = $request->input('sip');
            $customer_id = $request->input('customer_id');
            $network_id = $request->input('network_id');
            $switchConnection = 'switch';
            $done_by = auth()->user()->id;


            $matchingEntry = DB::connection($switchConnection)
                ->table('clientsshared')
                ->where('login', '=', $sip)
                ->first();

            if ($matchingEntry && $matchingEntry->type % 2 == 0) {
                return redirect()->back()->with('error', 'Inconsistent State: Cannot deactivate SIP');
            }

            if ($matchingEntry) {
                $newTypeValue = $matchingEntry->type - 1;

                DB::connection($switchConnection)
                    ->table('clientsshared')
                    ->where('login', '=', $sip)
                    ->update(['type' => $newTypeValue]);
            }


            $networkDetails = NetworkDetails::where('sip', $sip)
                ->update(['switch_status' => 0]);

            SipConnectionHistory::create([
                'sip' => $sip,
                'customer_id' => $customer_id,
                'done_by' => $done_by,
                'is_active' => 0,
                'status' => 1,
                'type' => 0,
                'network_id' => $network_id,
            ]);

            if ($matchingEntry && $networkDetails) {
                return redirect()->back()->with('success', 'SIP deactivated successfully');
            } else {
                return redirect()->back()->with('error', 'Failed to deactivate SIP');
            }
        } catch (\Exception $e) {
            Log::error('Exception: ' . $e->getMessage());
            dd($e);
        }
    }

    public function serverSuspendSip(Request $request)
    {
        try {
            $sip = $request->input('sip');
            $customer_id = $request->input('customer_id');
            $network_id = $request->input('network_id');
            $switchConnection = 'switch';
            $done_by = auth()->user()->id;


            $matchingEntry = DB::connection($switchConnection)
                ->table('clientsshared')
                ->where('login', '=', $sip)
                ->first();

            if ($matchingEntry && $matchingEntry->type % 2 == 0) {
                return redirect()->back()->with('error', 'Inconsistent State: Cannot deactivate SIP');
            }

            if ($matchingEntry) {
                $newTypeValue = $matchingEntry->type - 1;

                DB::connection($switchConnection)
                    ->table('clientsshared')
                    ->where('login', '=', $sip)
                    ->update(['type' => $newTypeValue]);
            }


            $networkDetails = NetworkDetails::where('sip', $sip)
                ->update(['switch_status' => 2]);

            SipConnectionHistory::create([
                'sip' => $sip,
                'customer_id' => $customer_id,
                'done_by' => $done_by,
                'is_active' => 0,
                'status' => 1,
                'type' => 2,
                'network_id' => $network_id,
            ]);

            if ($matchingEntry && $networkDetails) {
                return redirect()->back()->with('success', 'SIP Suspended successfully');
            } else {
                return redirect()->back()->with('error', 'Failed to Suspend SIP');
            }
        } catch (\Exception $e) {
            Log::error('Exception: ' . $e->getMessage());
            dd($e);
        }
    }

    public function serverReactivateSip(Request $request)
    {

        try {
            $sip = $request->input('sip');
            $customer_id = $request->input('customer_id');
            $switchConnection = 'switch';
            $done_by = auth()->user()->id;
            $network_id = $request->input('network_id');


            $matchingEntry = DB::connection($switchConnection)
                ->table('clientsshared')
                ->where('login', '=', $sip)
                ->first();

            if ($matchingEntry && $matchingEntry->type % 2 == 1) {
                return redirect()->back()->with('error', 'Inconsistent State: Cannot activate SIP');
            }

            if ($matchingEntry) {
                $newTypeValue = $matchingEntry->type + 1;

                DB::connection($switchConnection)
                    ->table('clientsshared')
                    ->where('login', '=', $sip)
                    ->update(['type' => $newTypeValue]);
            }


            $networkDetails = NetworkDetails::where('sip', $sip)
                ->update(['switch_status' => 1]);

            SipConnectionHistory::create([
                'sip' => $sip,
                'customer_id' => $customer_id,
                'done_by' => $done_by,
                'is_active' => 1,
                'status' => 1,
                'type' => 1,
                'network_id' => $network_id,
            ]);

            if ($matchingEntry && $networkDetails) {
                return redirect()->back()->with('success', 'SIP Reactivated successfully');
            } else {
                return redirect()->back()->with('error', 'Failed to reactivate SIP');
            }
        } catch (\Exception $e) {
            Log::error('Exception: ' . $e->getMessage());
            dd($e);
        }
    }


    public function addSingleCli(Request $request)
    {
        $validatedData = $request->validate([
            'cli_number' => 'required|string',
            'sip' => 'required|numeric',
            'network_id' => 'required|numeric',
        ]);

        $switchConnection = 'switch';

        $existingCli = DB::connection($switchConnection)->table('clientscallbackphones')
            ->where('phone_number', $validatedData['cli_number'])
            ->first();


        if ($existingCli) {

            $clientSharedInfo = DB::connection($switchConnection)->table('clientsshared')
                ->where('id_client', $existingCli->id_client)
                ->first();


            $errorMessage = "Phone number " . $existingCli->phone_number . " already exists in the switch database for client ID: " . $existingCli->id_client . " and SIP: " . $clientSharedInfo->login;
            return redirect()->back()->with('error', $errorMessage);
        }

        $clientSharedEntry = DB::connection($switchConnection)->table('clientsshared')
            ->where('login', $validatedData['sip'])
            ->first();

        $id_client = $clientSharedEntry->id_client;

        DB::connection($switchConnection)->table('clientscallbackphones')->insert([
            'phone_number' => $validatedData['cli_number'],
            'id_client' => $id_client,
            'def' => 0,
            'client_type' => 32,
        ]);

        $networkDetails = NetWorkDetails::find($validatedData['network_id']);




        $cliNumber = new CliNumbers([
            'network_id' => $validatedData['network_id'],
            'cli_number' => $validatedData['cli_number'],
            'sip' => $validatedData['sip'],
            'is_active' => 1,
        ]);

        $cliNumber->save();

        if ($networkDetails) {
            $existingCLINumbers = $networkDetails->cli_numbers ?? '';

            $newCLINumber = $validatedData['cli_number'];
            $updatedCLINumbers = $existingCLINumbers ? "$existingCLINumbers,$newCLINumber" : $newCLINumber;

            $networkDetails->update([
                'cli_numbers' => $updatedCLINumbers,
            ]);


            return redirect()->back()->with('success', 'CLI number added successfully');
        } else {
            return redirect()->back()->with('error', 'Network details not found');
        }
    }

    public function removeSingleCli(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'cli_number' => 'required|string',
                'sip' => 'required|numeric',
                'network_id' => 'required|numeric',
            ]);

            $switchConnection = 'switch';

            DB::connection($switchConnection)->table('clientscallbackphones')
                ->where('phone_number', $validatedData['cli_number'])
                ->delete();

            $cliEntry = CliNumbers::where('cli_number', $validatedData['cli_number'])
                ->where('network_id', $validatedData['network_id'])
                ->first();

            if ($cliEntry) {
                $cliEntry->update(['is_active' => 0]);
            }

            return redirect()->back()->with('success', 'CLI number removed successfully');
        } catch (QueryException $ex) {
            return redirect()->back()->with('error', 'An error occurred while removing CLI number.');
        }
    }


    public function removeSingleIncomingNumber(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'incoming_number' => 'required|string',
                'incoming_number_id' => 'required',
                'network_id' => 'required|numeric',
                'id_client' => 'required',
            ]);

            $switchConnection = 'switch';

            DB::connection($switchConnection)->table('redirectphones')
                ->where('id_client', $validatedData['id_client'])
                ->delete();

            $IncomingEntry = IncomingForwardingNumbers::where('id', $validatedData['incoming_number_id'])
                ->first();

            if ($IncomingEntry) {
                $IncomingEntry->update(['is_active' => 0]);
            }

            return redirect()->back()->with('success', 'Incoming Forwarding number removed successfully');
        } catch (QueryException $ex) {
            return redirect()->back()->with('error', 'An error occurred while removing Incoming Forwarding number.');
        }
    }

    public function assignPortSip(Request $request)
    {
        try {
            $request->validate([
                'selected_port' => 'required',
                'user_id' => 'required',
                'sip' => 'required',
                'customer_refno' => 'required',
            ]);

            $selectedPort = $request->input('selected_port');
            $network_id = $request->input('network_id');
            $selectedPortID = $request->input('selected_port');
            $userId = $request->input('user_id');
            $sip = $request->input('sip');
            $customerRefNo = $request->input('customer_refno');
            $switchConnection = 'switch';


            $selectedPortEntry = Ports::find($selectedPort);

            if ($selectedPortEntry) {
                $concatenatedPortValue = $selectedPortEntry->port_prefix . $selectedPortEntry->gateway . $selectedPortEntry->gateway_channel;
                $gatewayFinalNo = $selectedPortEntry->gateway;
            }

            $currentTechPrefix = DB::connection($switchConnection)->table('clientsshared')
                ->where('login', $sip)
                ->value('tech_prefix');

            if (str_contains($currentTechPrefix, "CP:0->+")) {
                $start = strpos($currentTechPrefix, ">", strpos($currentTechPrefix, ">", 2 - 1) + strlen(">")) + 1;
            } else {
                $start = strpos($currentTechPrefix, ">") + 1;
            }

            $currentPort = "";
            while (is_numeric($currentTechPrefix[$start])) {
                $currentPort .= $currentTechPrefix[$start];
                $start++;
            }

            $newPort = $concatenatedPortValue;

            $currentPort = $this->_get_existing_telephone_no($currentTechPrefix);

            $updatedTechPrefix = str_replace($currentPort, $newPort, $currentTechPrefix);

            $matchingEntry = DB::connection($switchConnection)->table('dialingplan')
                ->where('telephone_number', $currentPort)
                ->first();

            if ($matchingEntry && $matchingEntry->priority != 0) {
                DB::connection($switchConnection)->table('dialingplan')
                    ->where('telephone_number', $currentPort)
                    ->delete();
            }


            DB::connection($switchConnection)->table('clientsshared')
                ->where('login', $sip)
                ->update(['tech_prefix' => $updatedTechPrefix]);

            $telephoneNumber = $concatenatedPortValue;

            $gateway = $request->input('gateway_no');
            $routeData = DB::connection($switchConnection)
                ->table('gateways')
                ->where('description', $gatewayFinalNo)
                ->value('id_route');

            $idRoute = $routeData;


            // $data = [
            //     'telephone_number' => $telephoneNumber,
            //     'priority' => 0,
            //     'route_type' => 0,
            //     'tech_prefix' => "",
            //     'dial_as' => "",
            //     'id_route' => $idRoute,
            //     'call_type' => 1216348160,
            //     'type' => 0,
            //     'from_day' => 0,
            //     'to_day' => 6,
            //     'from_hour' => 0000,
            //     'to_hour' => 2400,
            //     'balance_share' => 100,
            //     'fields' => "From: t[fm1] <sip:t[fm2]@t[fm5]>",
            //     'call_limit' => 1,
            //     'id_dial_plan' => 1,
            // ];

            // DB::connection($switchConnection)->table('dialingplan')->insert($data);

            AllocatedPorts::create([
                'port_id' => $selectedPortID,
                'customer_id' => $userId,
                'priority' => 0,
                'sip' => $sip,
            ]);

            $port = Ports::find($selectedPort);

            if ($port) {
                $port->increment('allocated_users');
                $port->is_used = 1;
                $port->save();
            }

            $gatewayId = $port->gateway_id;
            $gatewayPort = Gateways::where('id', $gatewayId)->first();


            if ($gatewayPort) {
                $gatewayPort->port_usage += 1;
                $gatewayPort->save();
            }

            PortAssignHistory::create([
                'customer_id' => $userId,
                'assigned_port' => $concatenatedPortValue,
                'done_by' => auth()->id(),
                'sip' => $sip,
                'network_id' => $network_id,
                'is_active' => 1,
                'status' => 1,
            ]);

            return redirect()->back()->with('success', 'Port assigned successfully.');
        } catch (\Exception $ex) {
            dd($ex);
            Log::error("Error assigning port and SIP: " . $ex->getMessage());
            return redirect()->back()->with('error', 'An error occurred while adding the port.');
        }
    }

    public function addIncomingForwardingNumber(Request $request)
    {
        $request->validate([
            'incoming_forwarding_number' => 'required',
            'network_id' => 'required',
            'sip' => 'required',
        ]);

        $switchConnection = 'switch';

        $number = $request->input('incoming_forwarding_number');

        $incomingNumber = new IncomingForwardingNumbers;
        $incomingNumber->number = $number;
        $incomingNumber->network_id = $request->input('network_id');
        $incomingNumber->sip = $request->input('sip');
        $incomingNumber->is_active = 1;
        $incomingNumber->added_by = Auth::id();
        $incomingNumber->save();

        DB::connection($switchConnection)->table('redirectphones')->insert([
            'id_client' => $request->input('id_client'),
            'client_type' => 32,
            'call_end_reason' => 7,
            'number_priority' => 0,
            'follow_me_number' => "<answering_rule><action type=\"forward\"><forward_to>int$number</forward_to></action></answering_rule>",
        ]);

        return redirect()->back()->with('success', 'Incoming forwarding number added successfully');
    }







    public function _get_existing_telephone_no($current_tech_prefix)
    {
        if (str_contains($current_tech_prefix, "CP:0->+")) {
            $start = strpos($current_tech_prefix, ">", strpos($current_tech_prefix, ">", 2 - 1) + strlen(">")) + 1;
        } else {
            $start = strpos($current_tech_prefix, ">") + 1;
        }

        $current_port = "";
        while (is_numeric($current_tech_prefix[$start])) {
            $current_port .= $current_tech_prefix[$start];
            $start++;
        }

        return $current_port;
    }

    public function _add_incoming_dialplan_for_primary_port($telephoneNumber, $gateway)
    {
        $routeData = "123";
        // $idRoute = !empty($routeData) ? $routeData['id_route'] : '';

    }


    public function updatePaymentReminder(Request $request)
    {
        try {
            $user = Auth::user();
            $enablePaymentReminder = $request->input('enablePaymentReminder');

            $user->payment_reminders = $enablePaymentReminder ? 1 : 0;
            $user->save();

            $message = $enablePaymentReminder ? 'Payment Reminder turned back on, We will send you a payment reminder mail if you have any outstanding reminders' : 'Payment Reminders turned off, you wont be getting any payment reminding mails from now on';

            return redirect()->back()->with('success', $message);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error updating payment reminder setting');
        }
    }


    public function viewMySipAccounts()
    {

        $userId = Auth::id();
        $user = User::find($userId);
        $switchConnection = 'switch';
        $matchingNetwork = NetworkDetails::where('customer_id', $userId)->get()->first();
        $matchingNetworkDetails = NetworkDetails::where('customer_id', $userId)->get();
        $sipValue = $matchingNetwork->sip ?? '';

        $matchingClientsDetails = DB::connection($switchConnection)->table('clientsshared')
            ->where('login', $sipValue)
            ->select('id_client', 'account_state', 'tech_prefix')
            ->get();


        if ($matchingNetwork) {
            $matchingClis = CliNumbers::where('network_id', $matchingNetwork->id)
                ->where('is_active', 1)
                ->get();
        }

        $allGateways = Gateways::all();
        return view('network.all-sips-customer', [

            'matchingNetwork' => $matchingNetwork,

            'matchingClientsDetails' => $matchingClientsDetails,
            'matchingClis' => $matchingClis ?? NULL,

            'allGateways' => $allGateways,
            'matchingNetworkDetails' => $matchingNetworkDetails,
            'user' => $user,

        ]);
    }



    public function manageMyNetwork($userId, $networkID)
    {
        try {
            $user = User::find($userId);

            $userInvoices = CustomerInvoices::where('customer_id', $userId)->get();

            $userEmail = $user->email;

            $wallet = CustomerWallet::where('user_id', $userId)->first();
            $outstandingBalance = User::where('id', $userId)->first();

            $customerInvoicesCount = CustomerInvoices::where('customer_id', $userId)->count();
            $customerSubscriptoinsCount = SubscriptionNew::where('email', $userEmail)->count();


            $customerInvoices = CustomerInvoices::where('customer_id', $userId)->get();

            $totalUnpaidInvoiceAmount = CustomerInvoices::where('customer_id', $userId)
                ->where('pay_status', 'Not Paid')
                ->sum('total');

            $partiallyPaidInvoices = CustomerInvoices::where('customer_id', $userId)
                ->where('pay_status', 'Partially Paid')
                ->get(['total', 'paid_amount']);

            $totalUnpaidPartialInvoices = $partiallyPaidInvoices->sum(function ($invoice) {
                return ($invoice->total) - $invoice->paid_amount;
            });


            $totalUnpaidInvoiceAmount = ($totalUnpaidInvoiceAmount) + $totalUnpaidPartialInvoices;

            $totalUnpaidInvoiceAmountFinal = $totalUnpaidInvoiceAmount;

            $customerTokens = BarclaycardCustomerPaymentTokens::where('customer_id', $userId)->get(['card_mask', 'expiration_date', 'brand']);

            $switchConnection = 'switch';

            $latestPaymentReminder = OutstandingPaymentReminderMails::where('customer_id', $userId)
                ->latest('created_at')
                ->first();

            if ($outstandingBalance) {
                $outstandingBalanceAmount = $outstandingBalance->outstanding_balance;
            } else {
                $outstandingBalanceAmount = 0;
            }

            if ($wallet) {
                $walletAmount = $wallet->amount;
            } else {
                $walletAmount = 0;
            }
            $matchingNetwork = NetworkDetails::where('id', $networkID)->get()->first();
            $matchingSipPinRegenerations = SipAccountPinRegenerations::where('network_id', $networkID)->get();
            $matchingSipSipRegenerations = SipAccountSipRegenerations::where('network_id', $networkID)->get();
            $matchingSipConnectionHistory = SipConnectionHistory::where('network_id', $networkID)->get();
            $matchingPortAssignHistory = PortAssignHistory::where('network_id', $networkID)->get();

            $sipValue = $matchingNetwork->sip ?? '';

            $currentTechPrefix = DB::connection($switchConnection)->table('clientsshared')
                ->where('login', $sipValue)
                ->value('tech_prefix');

            if (str_contains($currentTechPrefix, "CP:0->+")) {
                $start = strpos($currentTechPrefix, ">", strpos($currentTechPrefix, ">", 2 - 1) + strlen(">")) + 1;
            } else {
                $start = strpos($currentTechPrefix, ">") + 1;
            }

            $currentPort = "";
            while (is_numeric($currentTechPrefix[$start])) {
                $currentPort .= $currentTechPrefix[$start];
                $start++;
            }


            $currentPort = $this->_get_existing_telephone_no($currentTechPrefix);

            $switchPortCount = DB::connection($switchConnection)
                ->table('clientsshared')
                ->where('tech_prefix', 'LIKE', '%' . $currentPort . '%')
                ->count();

            $matchingClientsDetails = DB::connection($switchConnection)
                ->table('clientsshared')
                ->where('login', $sipValue)
                ->select('id_client', 'account_state', 'tech_prefix', 'type')
                ->first();

            if ($matchingClientsDetails) {
                $idClient = $matchingClientsDetails->id_client;
                $accountState = $matchingClientsDetails->account_state;
                $acType = $matchingClientsDetails->type;
            } else {
            }
            $tariffs = DB::connection($switchConnection)->table('tariffsnames')->get();


            if ($matchingNetwork) {
                $matchingClis = CliNumbers::where('network_id', $matchingNetwork->id)
                    ->where('is_active', 1)
                    ->get();
            }
            $allGateways = Ports::all();
            $matchingIncomingForwardingNumbers = IncomingForwardingNumbers::where('network_id', $networkID)
                ->where('is_active', 1)
                ->get();

            $matchingCalls = DB::connection($switchConnection)
                ->table('calls')
                ->where('id_client', $idClient)
                ->get();

            return view('network.sip-details-customer', [
                'userInvoices' => $userInvoices,
                'user' => $user,
                'matchingCalls' => $matchingCalls,
                'walletAmount' => $walletAmount,
                'outstandingBalanceAmount' => $outstandingBalanceAmount,
                'customerInvoicesCount' => $customerInvoicesCount,
                'customerSubscriptoinsCount' => $customerSubscriptoinsCount,
                'customerTokens' => $customerTokens,
                'matchingNetwork' => $matchingNetwork,
                'matchingSipPinRegenerations' => $matchingSipPinRegenerations,
                'matchingSipSipRegenerations' => $matchingSipSipRegenerations,
                'matchingClientsDetails' => $matchingClientsDetails,
                'acType' => $acType,
                'accountState' => $accountState,
                'idClient' => $idClient,
                'matchingClis' => $matchingClis ?? NULL,
                'tariffs' => $tariffs,
                'allGateways' => $allGateways,
                'currentPort' => $currentPort,
                'switchPortCount' => $switchPortCount,
                'matchingIncomingForwardingNumbers' => $matchingIncomingForwardingNumbers,
                'totalUnpaidInvoiceAmountFinal' => $totalUnpaidInvoiceAmountFinal,
                'latestPaymentReminder' => $latestPaymentReminder,
                'matchingSipConnectionHistory' => $matchingSipConnectionHistory,
                'matchingPortAssignHistory' => $matchingPortAssignHistory,

            ]);
        } catch (\Exception $ex) {

            Log::error("AdminManageUserController (adminManageUser) : " . $ex);
            dd($ex);
            //return redirect()->route('dashboard/dashboard')->with('error', 'An error occurred.');
        }
    }

    public function viewAllSipAccounts()
    {
        $networkDetails = NetworkDetails::all(); // Assuming NetworkDetails is your model
        $users = User::all(); // Assuming Customer is your model for customers

        return view('network.all-sip-accounts', compact('networkDetails', 'users'));
    }



    public function topupSip(Request $request)
    {

        $globalTopupAmount = GlobalTopupAmount::first();

        if (!$globalTopupAmount) {
            return redirect()->back()->with('error', 'Topup configuration not found.');
        }

        $minAmount = $globalTopupAmount->min;
        $maxAmount = $globalTopupAmount->max;

        $inputAmount = $request->input('customer_amount_input');

        if ($inputAmount < $minAmount || $inputAmount > $maxAmount) {
            return redirect()->back()->with('error', "Topup amount must be between € $minAmount and € $maxAmount.");
        }

        $data['request_amount'] = number_format($request->customer_amount_input, 4);
        $data['network_id'] = $request->network_id;

        $networkDetails = NetworkDetails::find($request->network_id);
        $sipValue = $networkDetails->sip;

        $switchConnection = 'switch';
        $accountState = DB::connection($switchConnection)
            ->table('clientsshared')
            ->where('login', $sipValue)
            ->value('account_state');



        $data['account_state'] = $accountState;
        $data['sip'] = $sipValue;

        return view('network.sip-topup-payment-view-1', $data);
    }

    public function topupSipAdmin(Request $request)
    {
        $data['request_amount'] = number_format($request->customer_amount_input, 4);
        $data['network_id'] = $request->network_id;
        $data['user_id'] = $request->user_id;
        $data['user_email'] = $request->EMAIL;
        $data['user_name'] = $request->CN;

        $networkDetails = NetworkDetails::find($request->network_id);
        $sipValue = $networkDetails->sip;

        $switchConnection = 'switch';
        $accountState = DB::connection($switchConnection)
            ->table('clientsshared')
            ->where('login', $sipValue)
            ->value('account_state');



        $data['account_state'] = $accountState;
        $data['sip'] = $sipValue;

        return view('network.sip-topup-payment-view-2', $data);
    }

    public function submitFormToPaymentGatewaySipTopup(Request $request)
    {

        $ORDERID = $request->input('ORDERID');
        $PSPID = trans("constants.PSPID");
        $amount = $request->input('AMOUNT');
        $CURRENCY = trans("constants.CURRENCY");
        $LANGUAGE = trans("constants.LANGUAGE");
        $CN = $request->input('CN');
        $user_id = $request->input('user_id');
        $EMAIL = $request->input('EMAIL');
        $ACCEPTURL = route('accept-payment-sip-topup');
        $DECLINEURL = route('decline-payment');
        $EXCEPTIONURL = route('exception-payment');
        $CANCELURL = route('cancel-payment');
        $ALIAS = trans("constants.ALIAS");
        $ALIASUSAGE = trans("constants.ALIASUSAGE");
        $ALIASOPERATION = trans("constants.ALIASOPERATION");
        $ECI = trans("constants.ECI");
        $SWITCHACOUNTSTATE = $request->input('switch_account_state');
        $SIP = $request->input('sip');

        // Generate SHA signature
        $formParams = [
            'PSPID' => $PSPID,
            'ORDERID' => $ORDERID,
            'AMOUNT' => $amount,
            'CURRENCY' => $CURRENCY,
            'LANGUAGE' => $LANGUAGE,
            'CN' => $CN,
            'EMAIL' => $EMAIL,
            'ACCEPTURL' => $ACCEPTURL,
            'DECLINEURL' => $DECLINEURL,
            'EXCEPTIONURL' => $EXCEPTIONURL,
            'CANCELURL' => $CANCELURL,
            'ALIAS' => $ALIAS,
            'ALIASUSAGE' => $ALIASUSAGE,
            'ALIASOPERATION' => $ALIASOPERATION,
            'ECI' => $ECI
        ];

        $SHASIGN = $this->generateHash($formParams);

        $formParams['SHASIGN'] = $SHASIGN;


        session(['expected_order_id' => $ORDERID]);
        session(['expected_alias' => $ALIAS]);
        session(['expected_switch_account_state' => $SWITCHACOUNTSTATE]);
        session(['expected_sip' => $SIP]);

        session(['user_id' => $user_id]);

        return redirect()->away('https://payments.epdq.co.uk/ncol/prod/orderstandard_utf8.asp?' . http_build_query($formParams));
    }

    private function generateHash($mFormParams)
    {
        $password = trans("constants.TRANS_PASSWORD");
        ksort($mFormParams);
        $out = array();
        foreach ($mFormParams as $key => $param) {
            $out[] = strtoupper($key) . "=" . $param;
        }
        $out = implode($password, $out) . $password;
        return strtoupper(hash('sha1', $out));
    }


    public function setAcceptPaymentSipTopup(Request $request)
    {

        $existingInvoice = CustomerInvoices::where('invoice_number', $request->orderID)->first();

        if ($existingInvoice) {
            return redirect('https://callceylon-italy.com/dashboard/dashboard');
        }

        try {
            $data['orderID'] = $request->orderID;
            $data['currency'] = $request->currency;
            $data['amount'] = $request->amount;
            $data['PM'] = $request->PM;
            $data['CARDNO'] = $request->CARDNO;
            $data['CN'] = $request->CN;
            $data['TRXDATE'] = $request->TRXDATE;
            $data['PAYID'] = $request->PAYID;
            $data['BRAND'] = $request->BRAND;
            $email = $request->EMAIL;
            $userId = session('user_id');

            $paymentToken = BarclaycardCustomerPaymentTokens::create([
                'order_id' => $data['orderID'],
                'card_mask' => $data['CARDNO'],
                'customer_number' => $data['CN'],
                'currency' => $data['currency'],
                'brand' => $data['BRAND'],
                'expiration_date' => $request->ED,
                'email' => $email,
                'customer_id' => $userId,
                'is_primary' => 1,
            ]);

            BarclaycardCustomerPaymentTokens::where('customer_id', $userId)
                ->where('id', '!=', $paymentToken->id)
                ->update(['is_primary' => 0]);

            $expectedOrderID = session('expected_order_id');
            $expectedAlias = session('expected_alias');
            $expectedAccountState = session('expected_switch_account_state');
            $expectedSip = session('expected_sip');

            $switchConnection = 'switch';

            $formattedAmount = number_format($request->amount, 4);


            $accountState = DB::connection($switchConnection)
                ->table('clientsshared')
                ->where('login', $expectedSip)
                ->value('account_state');

            $idClient = DB::connection($switchConnection)
                ->table('clientsshared')
                ->where('login', $expectedSip)
                ->value('id_client');

            DB::connection($switchConnection)
                ->table('clientsshared')
                ->where('login', $expectedSip)
                ->update(['account_state' => DB::raw('account_state + ' . $formattedAmount)]);

            $paymentsData = [
                'id_client' => $idClient,
                'client_type' => 32,
                'money' => $request->amount,
                'data' => now()->toDateTimeString(),
                'type' => 1,
                'description' => $request->orderID,
                'invoice_id' => 0,
                'actual_value' => $accountState,
                'id_plan' => NULL,
                'id_payment_tag' => NULL,
                'module' => 'VSM',
                'id_module_user' => 1,
            ];

            DB::connection($switchConnection)
                ->table('payments')
                ->insert($paymentsData);


            $user = User::find($request->user()->id);
            $customer_name = $user->first_name . ' ' . $user->last_name . '-' . $user->customer_refno;



            $customerInvoice = CustomerInvoices::create([
                'customer_name' =>  $request->CN,
                'invoice_number' => $request->orderID,
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->toDateString(),
                'paid_date' => now()->toDateString(),
                'admin_note' => 'Sip Topup',
                'total' => $request->amount,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
                'customer_id' => $userId,
                'pay_status' => 'Active',
                'inv_type' => 1,
                'epdq_refno' => $request->PAYID,
                'admin_customer_note' => 'Sip Topup invoice (This is an automatic system-generated invoice)',
            ]);


            InvoiceSubscriptionDetails::create([
                'invoice_id' => $customerInvoice->id,
                'subscription_id' => 74,
                'description' => 'SIP Topup',
                'long_description' => 'SIP Topup',
                'cost' => $request->amount,
                'qty' => 1,
                'amount' => $request->amount,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($request->orderID === $expectedOrderID) {
                $paymentToken = BarclaycardCustomerPaymentTokens::where('order_id', $request->orderID)->first();

                if ($paymentToken) {
                    if (!$paymentToken->token) {
                        $paymentToken->token = $expectedAlias;
                        $paymentToken->save();
                    }
                }
            }

            $userId = Auth::id();
            $amount = $request->amount;

            SipTopupHistory::create([
                'sip' => $expectedSip,
                'customer_id' => $userId,
                'invoice_id' => $customerInvoice->id,
                'topup_by' => $userId,
                'payment_type' => 1,
                'amount' => $request->amount,
            ]);

            $this->setPayment($request);

            return view('frontend.view-accept-payment', $data);
        } catch (\Throwable $e) {

            return json_encode([
                "status" => '400',
                "message" => $e->getMessage()
            ]);
        }
    }


    public function setPayment($request)
    {
        try {

            //            DB::transaction(function () use ($request) {
            if (!OutstandingTrans::where('paid_refno', '=', $request->orderID)->exists()) {

                $trans = OutstandingTrans::create([
                    "email" => Auth::user()->email,
                    "customer_refno" => Auth::user()->customer_refno,
                    "outstanding_balance" => Auth::user()->outstanding_balance,
                    "outstanding_date" => Auth::user()->outstanding_date,
                    "is_paid" => 1,
                    "record_status" => 1,
                    "version" => 1,
                    "paid_refno" => $request->orderID,
                    "paid_date" => date('Y-m-d h:m:s'),
                    "currency" => $request->currency,
                    "amount" => $request->amount,
                    "pm" => $request->PM,
                    "acceptance" => $request->ACCEPTANCE,
                    "status" => $request->STATUS,
                    "cardno" => $request->CARDNO,
                    "ed" => $request->ED,
                    "cn" => $request->CN,
                    "trxdate" => $request->TRXDATE,
                    "payid" => $request->PAYID,
                    "ncerror" => $request->NCERROR,
                    "brand" => $request->BRAND,
                    "ip" => $request->IP,
                    "shasign" => $request->SHASIGN,
                    "customer_id" => Auth::user()->id,
                    "payment_for" => "Wallet",
                ]);



                $sub = Subscription::where(['orderid' => $request->orderID])->first();

                if ($sub != null) {
                    $sub->record_status = 1;
                    $sub->update();
                } else {
                    $trans_refid = $trans->id;

                    $balance = Auth::user()->outstanding_balance - $request->amount;
                    $profile = User::where(['customer_refno' => Auth::user()->customer_refno])->first();
                    $outstanding_date = $profile->outstanding_date;
                    $profile->outstanding_balance = $balance;
                    $profile->previous_out_bal = $balance;
                    $profile->previous_out_date = $outstanding_date;
                    $profile->is_paid = 1;
                    $profile->trans_refid = $trans_refid;
                    $profile->version = $profile->version + 1;
                    $profile->update();

                    $data["email"] = Auth::user()->email;
                    $data["title"] = "Payment Invoice";
                    $data["name"] = Auth::user()->name;
                    $data["order_id"] = $request->orderID;
                    $data["pm"] = $request->PM;
                    $data["brand"] = $request->BRAND;
                    $data["cardNo"] = $request->CARDNO;
                    $data["payId"] = $request->PAYID;
                    $data["cn"] = $request->CN;
                    $data["currency"] = $request->currency;
                    $data["amount"] = $request->amount;

                    // Mail::send('mail.Test_mail', $data, function($message)use($data) {
                    //     $message->to($data["email"])
                    //         ->subject($data["title"]);
                    // });

                }
            }
            //                DB::commit();
            //            });
        } catch (\Throwable $e) {

            return json_encode([
                "status" => '400',
                "message" => $e->getMessage()
            ]);
        }
    }



    public function disableSipTopupStatus(Request $request)
    {
        $networkId = $request->input('network_id');

        $networkDetails = NetworkDetails::where('id', $networkId)->first();

        if ($networkDetails) {
            $networkDetails->update(['sip_topup_status' => 0]);


            return redirect()->back()->with('success', 'SIP top-up status disabled successfully');
        } else {
            return redirect()->back()->with('error', 'Network ID not found');
        }
    }

    public function enableSipTopupStatus(Request $request)
    {
        $networkId = $request->input('network_id');

        $networkDetails = NetworkDetails::where('id', $networkId)->first();

        if ($networkDetails) {
            $networkDetails->update(['sip_topup_status' => 1]);


            return redirect()->back()->with('success', 'SIP top-up status enabled successfully');
        } else {
            return redirect()->back()->with('error', 'Network ID not found');
        }
    }

    public function disableCronMailStatus(Request $request)
    {
        $networkId = $request->input('network_id');

        $networkDetails = NetworkDetails::where('id', $networkId)->first();

        if ($networkDetails) {
            $networkDetails->update(['low_balalnce_auto_mail_status' => 0]);


            return redirect()->back()->with('success', 'SIP Cron sending status disabled successfully');
        } else {
            return redirect()->back()->with('error', 'Network ID not found');
        }
    }


    public function enableCronMailStatus(Request $request)
    {
        $networkId = $request->input('network_id');

        $networkDetails = NetworkDetails::where('id', $networkId)->first();

        if ($networkDetails) {
            $networkDetails->update(['low_balalnce_auto_mail_status' => 1]);


            return redirect()->back()->with('success', 'SIP Cron sending status enabled successfully');
        } else {
            return redirect()->back()->with('error', 'Network ID not found');
        }
    }


    public function deleteUnknownSip(Request $request)
    {

        $request->validate([
            'login' => 'required|string',
        ]);

        $login = $request->input('login');

        try {

            DB::connection('switch')->table('clientsshared')->where('login', $login)->delete();


            return redirect()->back()->with('success', 'SIP deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete SIP: ' . $e->getMessage());
        }
    }

    public function deactivatePaymentReminder(Request $request, $userId)
    {
        $user = User::find($userId);
        if ($user) {
            $user->payment_reminders = 0;
            $user->payment_reminder_deactivate_reason = $request->deactivation_reason; // Save deactivation reason
            $user->save();
            return redirect()->back()->with('success', 'Payment reminders deactivated!');
        }
        return redirect()->back()->with('error', 'User not found.');
    }

    public function reactivatePaymentReminder(Request $request, $userId)
    {
        $user = User::find($userId);
        if ($user) {
            $user->payment_reminders = 1;
            $user->save();
            return redirect()->back()->with('success', 'Payment reminders deactivated!');
        }
        return redirect()->back()->with('error', 'User not found.');
    }

    public function adminaddSip(Request $request)
    {
        // Validate request
        $request->validate([
            'customerId' => 'required|exists:users,id',
            'sip' => 'required|string',
        ]);

        // Match SIP with password in 'clientsshared' table
        $password = DB::connection('switch')
            ->table('clientsshared')
            ->where('login', $request->sip)
            ->value('password');

        if ($password == null) {
            return redirect()->back()->with('error', 'Entered SIP Incorrect');
        }
        $customerRefNo = User::where('id', $request->customerId)
            ->value('customer_refno');

        // Save to NetworkDetails model
        $networkDetail = NetworkDetails::create([
            'customer_id' => $request->customerId,
            'sip' => $request->sip,
            'pin' => $password,
            'customer_refno' => $customerRefNo,
        ]);

        SipAccountSipRegenerations::create([
            'network_id' => $networkDetail->id,
            'sip' => $request->sip,
            'regerated_by' => auth()->user()->id,
            'customer_id' => $request->customerId,
        ]);

        return redirect()->back()->with('success', 'SIP added successfully!');
    }


    public function sipTopupUsingWallet(Request $request)
    {

        try {
            $expectedSip = $request->input('sip');
            $switchConnection = 'switch';
            $formattedAmount = number_format($request->input('amount'), 4);
            $walletID = $request->input('wallet_id');
            $inputWalletAmount = $request->input('amount');

            $wallet = \App\Models\CustomerWallet::where('id', $walletID)->first();

            // Check if wallet amount is sufficient
            if ($wallet->amount < $inputWalletAmount) {
                return redirect()->back()->with('error', 'Insufficient wallet balance');
            }

            $order_id = 'SWINV0' . Carbon::now()->timestamp . rand(0, 999);
            $accountState = DB::connection($switchConnection)
                ->table('clientsshared')
                ->where('login', $expectedSip)
                ->value('account_state');

            $idClient = DB::connection($switchConnection)
                ->table('clientsshared')
                ->where('login', $expectedSip)
                ->value('id_client');

            DB::connection($switchConnection)
                ->table('clientsshared')
                ->where('login', $expectedSip)
                ->update(['account_state' => DB::raw('account_state + ' . $formattedAmount)]);

            $paymentsData = [
                'id_client' => $idClient,
                'client_type' => 32,
                'money' => $request->amount,
                'data' => now()->toDateTimeString(),
                'type' => 1,
                'description' => $order_id,
                'invoice_id' => 0,
                'actual_value' => $accountState,
                'id_plan' => NULL,
                'id_payment_tag' => NULL,
                'module' => 'VSM',
                'id_module_user' => 1,
            ];

            DB::connection($switchConnection)
                ->table('payments')
                ->insert($paymentsData);

            $user = User::find($request->input('user_id'));
            $customer_name = $user->first_name . ' ' . $user->last_name . '-' . $user->customer_refno;

            $customerInvoice = CustomerInvoices::create([
                'customer_name' =>  $user->first_name . ' ' . $user->last_name . '-' . $user->customer_refno,
                'invoice_number' => $order_id,
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->toDateString(),
                'paid_date' => now()->toDateString(),
                'admin_note' => 'Sip Topup using wallet',
                'total' => $request->amount,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
                'customer_id' => $user->id,
                'pay_status' => 'Active',
                'inv_type' => 1,
                'epdq_refno' => $request->PAYID,
                'admin_customer_note' => 'Sip Topup invoice (This is an automatic system-generated invoice)',
            ]);

            InvoiceSubscriptionDetails::create([
                'invoice_id' => $customerInvoice->id,
                'subscription_id' => 74,
                'description' => 'SIP Topup',
                'long_description' => 'SIP Topup',
                'cost' => $request->amount,
                'qty' => 1,
                'amount' => $request->amount,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $userId = $request->input('user_id');
            $amount = $request->input('amount');

            SipTopupHistory::create([
                'sip' => $expectedSip,
                'customer_id' => $userId,
                'invoice_id' => $customerInvoice->id,
                'topup_by' => $userId,
                'payment_type' => 2,
                'amount' => $amount,
            ]);

            // Deduct the wallet amount from the wallet
            $wallet->amount -= $inputWalletAmount;
            $wallet->save();

            return redirect()->back()->with('success', "SIP Topped up using wallet successfully. {$inputWalletAmount} EUR has been deducted from the wallet, SIP topup invoice is {$order_id}");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function deactivateWallet(Request $request, $userId)
    {
        $user = User::find($userId);
        if ($user) {
            $user->show_wallet = 0;
            $user->save();
            return redirect()->back()->with('success', 'Wallet deactivated!');
        }
        return redirect()->back()->with('error', 'User not found.');
    }

    public function reactivateWallet(Request $request, $userId)
    {
        $user = User::find($userId);
        if ($user) {
            $user->show_wallet = 1;
            $user->save();
            return redirect()->back()->with('success', 'Wallet deactivated!');
        }
        return redirect()->back()->with('error', 'User not found.');
    }

    public function proxyPaymentSip(Request $request)
    {

        $url = 'https://payments.epdq.co.uk/ncol/prod/orderdirect_utf8.asp';
        $response = Http::asForm()->post($url, $request->all());


        $xml = simplexml_load_string($response->body());
        $array = json_decode(json_encode($xml), true);
        $switchConnection = 'switch';
        $orderID = $array['@attributes']['orderID'] ?? null;
        $ncError = $array['@attributes']['NCERROR'] ?? null;
        $ncErrorPlus = $array['@attributes']['NCERRORPLUS'] ?? null;
        $formattedAmountDivided = $request->input('AMOUNT');
        $formattedAmount = number_format($formattedAmountDivided, 4);

        if ($ncErrorPlus !== "!") {
            $ncErrorPlus = $array['@attributes']['NCERRORPLUS'] ?? 'An unknown error occurred.';

            FailedSipTopupsUsingToken::create([
                'xml' => $response->body(),
                'order_id' => $orderID,
                'done_by' => auth()->id()
            ]);

            return redirect()->back()->with('error', $ncErrorPlus);
        }

        SuccessSipTopupsUsingToken::create([
            'xml' => $response->body(),
            'order_id' => $orderID,
            'done_by' => auth()->id()
        ]);

        $order_id = $request->input('ORDERID');
        $expectedSip = $request->input('sip');
        $accountState = DB::connection($switchConnection)
            ->table('clientsshared')
            ->where('login', $expectedSip)
            ->value('account_state');

        $idClient = DB::connection($switchConnection)
            ->table('clientsshared')
            ->where('login', $expectedSip)
            ->value('id_client');

        DB::connection($switchConnection)
            ->table('clientsshared')
            ->where('login', $expectedSip)
            ->update(['account_state' => DB::raw('account_state + ' . $formattedAmount)]);

        $paymentsData = [
            'id_client' => $idClient,
            'client_type' => 32,
            'money' => $request->input('AMOUNT'),
            'data' => now()->toDateTimeString(),
            'type' => 1,
            'description' => $order_id,
            'invoice_id' => 0,
            'actual_value' => $accountState,
            'id_plan' => NULL,
            'id_payment_tag' => NULL,
            'module' => 'VSM',
            'id_module_user' => 1,
        ];

        DB::connection($switchConnection)
            ->table('payments')
            ->insert($paymentsData);

        $user = User::find($request->input('user_id'));
        $customer_name = $user->first_name . ' ' . $user->last_name . '-' . $user->customer_refno;

        $customerInvoice = CustomerInvoices::create([
            'customer_name' =>  $user->first_name . ' ' . $user->last_name . '-' . $user->customer_refno,
            'invoice_number' => $order_id,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'paid_date' => now()->toDateString(),
            'admin_note' => 'Sip Topup (Tokenization)',
            'total' => $request->input('AMOUNT'),
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
            'customer_id' => $user->id,
            'pay_status' => 'Active',
            'inv_type' => 1,
            'epdq_refno' => $orderID,
            'admin_customer_note' => 'Sip Topup invoice (This is an automatic system-generated invoice)',
        ]);

        InvoiceSubscriptionDetails::create([
            'invoice_id' => $customerInvoice->id,
            'subscription_id' => 74,
            'description' => 'SIP Topup',
            'long_description' => 'SIP Topup',
            'cost' => $request->input('AMOUNT'),
            'qty' => 1,
            'amount' => $request->input('AMOUNT'),
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $userId = $request->input('user_id');
        $amount = $request->input('AMOUNT');

        SipTopupHistory::create([
            'sip' => $expectedSip,
            'customer_id' => $userId,
            'invoice_id' => $customerInvoice->id,
            'topup_by' => $userId,
            'payment_type' => 3,
            'amount' => $amount,
        ]);

        return redirect()->back()->with('success', 'Payment processed successfully.');
    }


    public function serverRemovePort(Request $request)
    {
        try {
            $request->validate([
                'sip' => 'required',
                'user_id' => 'required',
            ]);

            $sip = $request->input('sip');
            $userId = $request->input('user_id');
            $switchConnection = 'switch';

            $allocatedPort = AllocatedPorts::where('sip', $sip)
                ->where('customer_id', $userId)
                ->first();

            $port = $allocatedPort ? Ports::find($allocatedPort->port_id) : null;

            $currentTechPrefix = DB::connection($switchConnection)->table('clientsshared')
                ->where('login', $sip)
                ->value('tech_prefix');

            if ($allocatedPort && !$port) {
                return redirect()->back()->with('error', 'Assigned port not found.');
            }

            if ($port) {
                $concatenatedPortValue = $port->port_prefix . $port->gateway . $port->gateway_channel;
            } else {
                preg_match('/DP:94->([0-9]+);TP/', $currentTechPrefix, $matches);
                if (!isset($matches[1])) {
                    return redirect()->back()->with('error', 'Unable to identify the port in tech_prefix.');
                }
                $concatenatedPortValue = $matches[1];
            }

            if (str_contains($currentTechPrefix, $concatenatedPortValue)) {
                $portStart = strpos($currentTechPrefix, $concatenatedPortValue);

                $beforePort = substr($currentTechPrefix, 0, $portStart); // Everything before the port
                $afterPort = substr($currentTechPrefix, $portStart + strlen($concatenatedPortValue)); // Everything after the port


                $firstDigit = $concatenatedPortValue[0];
                $updatedTechPrefix = $beforePort . $firstDigit . $afterPort;

                DB::connection($switchConnection)->table('clientsshared')
                    ->where('login', $sip)
                    ->update(['tech_prefix' => $updatedTechPrefix]);
            } else {
                return redirect()->back()->with('error', 'Assigned port not found in tech_prefix.');
            }

            if ($port) {
                $port->decrement('allocated_users');

                if ($port->allocated_users == 0) {
                    $port->is_used = 0;
                    $port->save();
                }

                $gatewayPort = Gateways::where('id', $port->gateway_id)->first();
                if ($gatewayPort) {
                    $gatewayPort->port_usage -= 1;
                    $gatewayPort->save();
                }

                PortAssignHistory::create([
                    'customer_id' => $userId,
                    'assigned_port' => $concatenatedPortValue,
                    'done_by' => auth()->id(),
                    'sip' => $sip,
                    'network_id' => $request->input('network_id'),
                    'is_active' => 0,
                    'status' => 0,
                ]);

                $allocatedPort->delete();
            }

            return redirect()->back()->with('success', 'Port removed successfully.');
        } catch (\Exception $ex) {
            Log::error("Error removing port and SIP: " . $ex->getMessage());
            return redirect()->back()->with('error', 'An error occurred while removing the port.');
        }
    }










    public function disableSipAutoTopupStatus(Request $request)
    {
        $networkId = $request->input('network_id');

        $networkDetails = NetworkDetails::where('id', $networkId)->first();

        if ($networkDetails) {
            $networkDetails->update(['auto_topup_status' => 0]);


            return redirect()->back()->with('success', 'SIP auto top-up status disabled successfully');
        } else {
            return redirect()->back()->with('error', 'Network ID not found');
        }
    }

    public function enableSipAutoTopupStatus(Request $request)
    {
        $networkId = $request->input('network_id');

        $networkDetails = NetworkDetails::where('id', $networkId)->first();

        if ($networkDetails) {
            $networkDetails->update(['auto_topup_status' => 1]);


            return redirect()->back()->with('success', 'SIP auto top-up status disabled successfully');
        } else {
            return redirect()->back()->with('error', 'Network ID not found');
        }
    }


    public function updateAutoTopupTriggerAmount(Request $request)
    {
        $networkId = $request->input('network_id');
        $triggerAmount = $request->input('topup_trigger_amount');
        $formattedTopupAmount = number_format($triggerAmount, 4, '.', '');

        $networkDetails = NetworkDetails::where('id', $networkId)->first();

        if ($networkDetails) {
            $networkDetails->update(['auto_topup_trigger_amount' => $formattedTopupAmount]);

            return redirect()->back()->with('success', 'SIP auto top-up status disabled successfully');
        } else {
            return redirect()->back()->with('error', 'Network ID not found');
        }
    }

    public function updateAutoTopupAmount(Request $request)
    {
        $networkId = $request->input('network_id');
        $topupAmount = $request->input('topup_amount');
        $formattedTopupAmount = number_format($topupAmount, 4, '.', '');


        $networkDetails = NetworkDetails::where('id', $networkId)->first();

        if ($networkDetails) {
            $networkDetails->update(['auto_topup_amount' => $formattedTopupAmount]);


            return redirect()->back()->with('success', 'SIP auto top-up status disabled successfully');
        } else {
            return redirect()->back()->with('error', 'Network ID not found');
        }
    }

    public function autoTopupCron()
    {
        $networkDetails = NetworkDetails::where('auto_topup_status', 1)
            ->whereNotNull('auto_topup_trigger_amount')
            ->where('auto_topup_trigger_amount', '!=', 0)
            ->whereNotNull('auto_topup_amount')
            ->where('auto_topup_amount', '>', 0)
            ->get();


        $switchConnection = 'switch';

        foreach ($networkDetails as $detail) {

            $sip = $detail->sip;

            $order_id = "AUTO-" . strtoupper(Str::random(10));

            $user = User::find($detail->customer_id);
            $userEmail = $user->email;

            $accountState = DB::connection($switchConnection)
                ->table('clientsshared')
                ->where('login', $sip)
                ->value('account_state');

            if ($accountState < $detail->auto_topup_trigger_amount) {
                $customerId = $detail->customer_id;
                $roundedto2TriggerAmount = round($detail->auto_topup_amount, 2);

                $barclaycardToken = BarclaycardCustomerPaymentTokens::where('customer_id', $customerId)
                    ->where('is_primary', 1)
                    ->first();


                if ($barclaycardToken) {
                    $params = [
                        'PSPID' => trans("constants.PSPID"),
                        'ORDERID' => $order_id,
                        'AMOUNT' => $roundedto2TriggerAmount,
                        'CURRENCY' => trans('constants.CURRENCY'),
                        'LANGUAGE' => trans('constants.LANGUAGE'),
                        'CN' => $barclaycardToken->customer_number ?? '',
                        'EMAIL' => $barclaycardToken->email ?? '',
                        'ACCEPTURL' => route('accept-payment'),
                        'DECLINEURL' => route('decline-payment'),
                        'EXCEPTIONURL' => route('exception-payment'),
                        'CANCELURL' => route('cancel-payment'),
                        'ALIAS' => $barclaycardToken->token,
                        'ALIASUSAGE' => trans('constants.ALIASUSAGE'),
                        'ALIASOPERATION' => trans('constants.ALIASOPERATION'),
                        'ECI' => trans('constants.ECI'),
                        'USERID' => trans('constants.USERID'),
                        'PSWD' => trans('constants.PSWD')
                    ];

                    $params['SHASIGN'] = $this->generateHashSipAutoTopup($params);

                    $result = $this->proxyPaymentAutoTopup($params);

                    if (isset($result['error']) && $result['error']) {

                        FailedSipAutoTopupsCron::create([
                            'xml' => $result['message'],
                            'sip' => $sip
                        ]);

                        Mail::send('mail.sip-auto-topup-failed-mail', [
                            'sip' => $sip,
                            'first_name' => $user->first_name,
                            'last_name' => $user->last_name,
                            'trigger_amount' => $detail->auto_topup_trigger_amount,
                            'topup_amount' => $detail->auto_topup_amount,
                            'masked_card' => $barclaycardToken->card_mask,
                            'date' => date('Y-m-d')
                        ], function ($message) use ($userEmail) {
                            $message->to($userEmail);
                            $message->subject('SIP Account Auto Topup Failure Notification');
                        });
                    } else {

                        $idClient = DB::connection($switchConnection)
                            ->table('clientsshared')
                            ->where('login', $sip)
                            ->value('id_client');

                        DB::connection($switchConnection)
                            ->table('clientsshared')
                            ->where('login', $sip)
                            ->update(['account_state' => DB::raw('account_state + ' . $detail->auto_topup_amount)]);

                        $paymentsData = [
                            'id_client' => $idClient,
                            'client_type' => 32,
                            'money' => $detail->auto_topup_amount,
                            'data' => now()->toDateTimeString(),
                            'type' => 1,
                            'description' => $order_id,
                            'invoice_id' => 0,
                            'actual_value' => $accountState,
                            'id_plan' => NULL,
                            'id_payment_tag' => NULL,
                            'module' => 'VSM',
                            'id_module_user' => 1,
                        ];

                        DB::connection($switchConnection)
                            ->table('payments')
                            ->insert($paymentsData);

                        $customer_name = $user->first_name . ' ' . $user->last_name . '-' . $user->customer_refno;

                        $customerInvoice = CustomerInvoices::create([
                            'customer_name' =>  $user->first_name . ' ' . $user->last_name . '-' . $user->customer_refno,
                            'invoice_number' => $order_id,
                            'invoice_date' => now()->toDateString(),
                            'due_date' => now()->toDateString(),
                            'paid_date' => now()->toDateString(),
                            'admin_note' => 'Sip Topup (Tokenization)',
                            'total' => $detail->auto_topup_amount,
                            'status' => 1,
                            'created_at' => now(),
                            'updated_at' => now(),
                            'customer_id' => $user->id,
                            'pay_status' => 'Active',
                            'inv_type' => 1,
                            'epdq_refno' => $order_id,
                            'admin_customer_note' => 'Sip Topup invoice (This is an automatic system-generated invoice)',
                        ]);

                        InvoiceSubscriptionDetails::create([
                            'invoice_id' => $customerInvoice->id,
                            'subscription_id' => 74,
                            'description' => 'SIP Topup',
                            'long_description' => 'SIP Topup',
                            'cost' => $detail->auto_topup_amount,
                            'qty' => 1,
                            'amount' => $detail->auto_topup_amount,
                            'status' => 1,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $userId = $detail->customer_id;
                        $amount = $detail->auto_topup_amount;

                        SipTopupHistory::create([
                            'sip' => $sip,
                            'customer_id' => $userId,
                            'invoice_id' => $customerInvoice->id,
                            'topup_by' => $userId,
                            'payment_type' => 4,
                            'amount' => $amount,
                        ]);

                        SuccessSipAutoTopupsCron::create([
                            'xml' => $result['message'],
                            'sip' => $sip
                        ]);

                        Mail::send('mail.sip-auto-topup-success-mail', [
                            'sip' => $sip,
                            'first_name' => $user->first_name,
                            'last_name' => $user->last_name,
                            'trigger_amount' => $detail->auto_topup_trigger_amount,
                            'topup_amount' => $detail->auto_topup_amount,
                            'masked_card' => $barclaycardToken->card_mask,
                            'date' => date('Y-m-d')
                        ], function ($message) use ($userEmail) {
                            $message->to($userEmail);
                            $message->subject('SIP Account Auto Topuped up successfully');
                        });
                    }
                }
            }
        }
    }

    public function proxyPaymentAutoTopup($params)
    {
        $url = 'https://payments.epdq.co.uk/ncol/prod/orderdirect_utf8.asp';
        $response = Http::asForm()->post($url, $params);

        $xml = simplexml_load_string($response->body());
        $array = json_decode(json_encode($xml), true);

        $orderID = $array['@attributes']['orderID'] ?? null;
        $ncErrorPlus = $array['@attributes']['NCERRORPLUS'] ?? null;

        if ($ncErrorPlus !== "!") {
            $errorMessage = $ncErrorPlus ?? 'An unknown error occurred.';
            return [
                'error' => true,
                'message' => $response->body()
            ];
        }

        return [
            'success' => true,
            'message' => $response->body()
        ];
    }

    private function generateHashSipAutoTopup($params)
    {
        $password = trans("constants.TRANS_PASSWORD");

        // Sort parameters alphabetically by keys
        ksort($params);

        // Construct the parameter string as expected by the payment gateway
        $paramString = "";
        foreach ($params as $key => $value) {
            if (strlen($value)) {  // Make sure that empty parameters are not included
                $paramString .= strtoupper($key) . "=" . $value . $password;
            }
        }

        // Generate SHA1 hash
        return strtoupper(hash('sha1', $paramString));
    }


    public function viewAllSipAutoTopupHistory()
    {

        $failedSipTopupsHistory = FailedSipAutoTopupsCron::all();

        $successSipTopupsHistory = SuccessSipAutoTopupsCron::all();
        $users = User::all(); // Assuming Customer is your model for customers

        return view('network.all-sip-accounts-topup-history', compact('failedSipTopupsHistory', 'successSipTopupsHistory', 'users'));
    }



    public function enableAutoSuspendSip(Request $request)
    {
        $networkId = $request->input('network_id');

        $networkDetails = NetworkDetails::where('id', $networkId)->first();

        if ($networkDetails) {
            $networkDetails->update(['auto_suspend' => 1]);


            return redirect()->back()->with('success', 'SIP auto suspend enabled successfully');
        } else {
            return redirect()->back()->with('error', 'Network ID not found');
        }
    }


    public function disableAutoSuspendSip(Request $request)
    {
        $networkId = $request->input('network_id');

        $networkDetails = NetworkDetails::where('id', $networkId)->first();

        if ($networkDetails) {
            $networkDetails->update(['auto_suspend' => 0]);


            return redirect()->back()->with('success', 'SIP auto suspend disabled successfully');
        } else {
            return redirect()->back()->with('error', 'Network ID not found');
        }
    }



    public function connectRecurringTemplateWithSip(Request $request)
    {
        $request->validate([
            'recurring_template_id' => 'required|exists:customer_recurring_templates,id',
            'network_id' => 'required|exists:network_details,id',
        ]);

        // Find the network detail and update the recurring_template_id
        $network = NetworkDetails::find($request->network_id);
        $network->recurring_template_id = $request->recurring_template_id;
        $network->invoice_deadline = $request->invoice_deadline;
        $network->save();

        return redirect()->back()->with('success', 'Recurring template connected successfully.');
    }

    public function suspenSipAccountCron()
    {
        $networkDetails = NetworkDetails::whereNotNull('recurring_template_id')->get();

        $currentMonthStart = Carbon::now()->startOfMonth();
        $currentMonthEnd = Carbon::now()->endOfMonth();

        $switchConnection = 'switch';

        foreach ($networkDetails as $networkDetail) {
            if (CustomerRecurringTemplates::where('id', $networkDetail->recurring_template_id)->exists()) {

                $recurredInvoices = RecurredInvoices::where('rec_inv_id', $networkDetail->recurring_template_id)
                    ->whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])
                    ->get();

                foreach ($recurredInvoices as $recurredInvoice) {
                    $customerInvoice = CustomerInvoices::where('id', $recurredInvoice->inv_id)->first();

                    if ($customerInvoice) {
                        $deadlineDate = Carbon::now()->startOfMonth()->addDays($networkDetail->invoice_deadline - 1);

                        if ($customerInvoice->pay_status != "Active" && Carbon::now()->greaterThan($deadlineDate)) {

                            $matchingClientsDetails = DB::connection($switchConnection)
                                ->table('clientsshared')
                                ->where('login', $networkDetail->sip)
                                ->select('id_client', 'account_state')
                                ->first();

                            SipAccountSuspends::create([
                                'network_id' => $networkDetail->id,
                                'sip' => $networkDetail->sip,
                                'account_state' => $matchingClientsDetails->account_state
                            ]);

                            $matchingPaymentDetails = DB::connection($switchConnection)
                                ->table('payments')
                                ->where('money', 'like', '%-%')
                                ->get();



                            dd($matchingPaymentDetails);
                        }
                    }
                }
            }
        }
    }



    public function addSingleSpeedDial(Request $request)
    {
        $validatedData = $request->validate([
            'speed_dial' => 'required|string',
            'sip' => 'required|numeric',
            'network_id' => 'required|numeric',
            'speeddial_count' => 'required|numeric',
        ]);

        $switchConnection = 'switch';

        $existingSpeedDial = DB::connection($switchConnection)->table('addressbook')
            ->where('telephone_number', $validatedData['speed_dial'])
            ->first();


        if ($existingSpeedDial) {

            $clientSharedInfo = DB::connection($switchConnection)->table('clientsshared')
                ->where('id_client', $existingSpeedDial->id_client)
                ->first();


            $errorMessage = "Speed Dial " . $existingSpeedDial->telephone_number . " already exists in the switch database for client ID: " . $existingSpeedDial->id_client . " and SIP: " . $clientSharedInfo->login;
            return redirect()->back()->with('error', $errorMessage);
        }

        $clientSharedEntry = DB::connection($switchConnection)->table('clientsshared')
            ->where('login', $validatedData['sip'])
            ->first();

        $id_client = $clientSharedEntry->id_client;



        DB::connection($switchConnection)->table('addressbook')->insert([
            'telephone_number' => $validatedData['speed_dial'],
            'id_client' => $id_client,
            'nickname' => null,
            'type' => 32,
            'speeddial' => $validatedData['speeddial_count'],
        ]);

        $networkDetails = NetWorkDetails::find($validatedData['network_id']);

        $speedDial = new speedDials([
            'network_id' => $validatedData['network_id'],
            'speed_dial' => $validatedData['speed_dial'],
            'sip' => $validatedData['sip'],
            'is_active' => 1,
        ]);

        $speedDial->save();

        if ($networkDetails) {
            $existingSpeedDials = $networkDetails->speed_dials ?? '';

            $newSpeedDial = $validatedData['speed_dial'];
            $updatedSpeedDials = $existingSpeedDials ? "$existingSpeedDials,$newSpeedDial" : $newSpeedDial;

            $networkDetails->update([
                'speed_dials' => $updatedSpeedDials,
            ]);


            return redirect()->back()->with('success', 'Speed Dial added successfully');
        } else {
            return redirect()->back()->with('error', 'Network details not found');
        }
    }



    public function removeSingleSpeedDial(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'speed_dial' => 'required|string',
                'sip' => 'required|numeric',
                'network_id' => 'required|numeric',
            ]);

            $switchConnection = 'switch';

            $test = DB::connection($switchConnection)->table('addressbook')
                ->where('telephone_number', $validatedData['speed_dial'])
                ->delete();


            $speedEntry = SpeedDials::where('speed_dial', $validatedData['speed_dial'])
                ->where('network_id', $validatedData['network_id'])
                ->first();

            if ($speedEntry) {
                $speedEntry->update(['is_active' => 0]);
            }

            return redirect()->back()->with('success', 'Speed dial removed successfully');
        } catch (QueryException $ex) {
            return redirect()->back()->with('error', 'An error occurred while removing speed dial number.');
        }
    }


    public function addDidToCustomer(Request $request)
    {
        $validated = $request->validate([
            'did_id' => 'required|integer',
            'customer_id' => 'required|integer',
        ]);
        // dd($request->all());

        try {
            $user = User::findOrFail($validated['customer_id']);

            if (!is_null($user->did_ids)) {
                $user->did_ids .= ',' . $validated['did_id'];
            } else {
                $user->did_ids = $validated['did_id'];
            }
            $user->save();

            $did = \App\Models\DidList::findOrFail($validated['did_id']);
            $did->is_allocated = 1;
            $did->save();

            return redirect()->back()->with('success', 'DID allocated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred: ' . $e->getMessage());
        }
    }




    public function customerPaymentMethods()
    {
        $users = User::where('is_active', 1)
            ->whereNotNull('email_verified_at')
            ->leftJoin('barclaycard_customer_payment_tokens', 'users.id', '=', 'barclaycard_customer_payment_tokens.customer_id')
            ->whereNull('barclaycard_customer_payment_tokens.customer_id')
            ->orderBy('users.created_at', 'desc')
            ->select('users.*')
            ->paginate(100);

        return view('manageuser.view-all-customer-payment-methods', compact('users'));
    }



    public function saveTopupAmounts(Request $request)
    {
        try {
            $network_id = $request->input('network_id');
            $topupAmounts = $request->input('topupAmounts');

            $networkDetails = NetworkDetails::where('id', $network_id)->first();

            if ($networkDetails) {
                $networkDetails->topup_amounts = $topupAmounts;
                $networkDetails->save();
            }

            return redirect()->back()->with('success', 'Topup amounts saved successfully.');
        } catch (\Exception $e) {
            Log::error('Error saving topup amounts: ' . $e->getMessage());

            return redirect()->back()->with('error', 'An error occurred while saving the topup amounts. Please try again.');
        }
    }



    public function saveSelectedPackageSip(Request $request)
    {
        try {
            $network_id = $request->input('network_id');
            $package_id = $request->input('package_id');

            $networkDetails = NetworkDetails::where('id', $network_id)->first();

            if ($networkDetails) {
                $networkDetails->package_id = $package_id;
                $networkDetails->save();
            }

            return redirect()->back()->with('success', 'Package selected successfully.');
        } catch (\Exception $e) {
            Log::error('Error saving selected package: ' . $e->getMessage());

            return redirect()->back()->with('error', 'An error occurred while saving the selected package. Please try again.');
        }
    }



    public function topupUsingCreditInvoice(Request $request)
    {

        try {
            $expectedSip = $request->input('sip');
            $switchConnection = 'switch';
            $formattedAmount = number_format($request->input('amount'), 4);


            $order_id = 'CSINV0' . Carbon::now()->timestamp . rand(0, 999);
            $accountState = DB::connection($switchConnection)
                ->table('clientsshared')
                ->where('login', $expectedSip)
                ->value('account_state');

            $idClient = DB::connection($switchConnection)
                ->table('clientsshared')
                ->where('login', $expectedSip)
                ->value('id_client');

            DB::connection($switchConnection)
                ->table('clientsshared')
                ->where('login', $expectedSip)
                ->update(['account_state' => DB::raw('account_state + ' . $formattedAmount)]);


            $paymentsData = [
                'id_client' => $idClient,
                'client_type' => 32,
                'money' => $request->amount,
                'data' => now()->toDateTimeString(),
                'type' => 1,
                'description' => $order_id,
                'invoice_id' => 0,
                'actual_value' => $accountState,
                'id_plan' => NULL,
                'id_payment_tag' => NULL,
                'module' => 'VSM',
                'id_module_user' => 1,
            ];

            DB::connection($switchConnection)
                ->table('payments')
                ->insert($paymentsData);

            $user = User::find($request->input('user_id'));
            $customer_name = $user->first_name . ' ' . $user->last_name . '-' . $user->customer_refno;

            $customerInvoice = CustomerInvoices::create([
                'customer_name' =>  $user->first_name . ' ' . $user->last_name . '-' . $user->customer_refno,
                'invoice_number' => $order_id,
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->toDateString(),
                'admin_note' => 'Sip Topup (Credit)',
                'total' => $request->amount,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
                'customer_id' => $user->id,
                'pay_status' => 'Not Paid',
                'inv_type' => 1,
                'admin_customer_note' => 'Sip Topup invoice (This is an automatic system-generated invoice)',
            ]);


            InvoiceSubscriptionDetails::create([
                'invoice_id' => $customerInvoice->id,
                'subscription_id' => 74,
                'description' => 'SIP Topup',
                'long_description' => 'SIP Topup',
                'cost' => $request->amount,
                'qty' => 1,
                'amount' => $request->amount,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $userId = $request->input('user_id');
            $amount = $request->input('amount');

            SipTopupHistory::create([
                'sip' => $expectedSip,
                'customer_id' => $userId,
                'invoice_id' => $customerInvoice->id,
                'topup_by' => $userId,
                'payment_type' => 5,
                'amount' => $amount,
            ]);


            return redirect()->back()->with('success', "An Invoice has been raised, and {$amount} EUR has been added to the SIP account.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }



    public function testtingVoip()
    {
        $switchConnection = 'switch';

        $columns = DB::connection($switchConnection)->getSchemaBuilder()->getColumnListing('addressbook');

        $entries = DB::connection($switchConnection)->table('addressbook')->take(10)->get();

        $result = [
            'columns' => $columns,
            'entries' => $entries
        ];

        dd($result);
    }






    public function outstandingPyamentCollectCron()
    {

        $notPaidInvoices = CustomerInvoices::where('pay_status', 'Not Paid')->get();

        foreach ($notPaidInvoices as $invoice) {

            $barclaycardToken = BarclaycardCustomerPaymentTokens::where('customer_id', $invoice->customer_id)
                ->where('is_primary', 1)
                ->first();

            if ($barclaycardToken) {
                $params = [
                    'PSPID' => trans("constants.PSPID"),
                    'ORDERID' => $invoice->invoice_number,
                    'AMOUNT' => $invoice->total,
                    'CURRENCY' => trans('constants.CURRENCY'),
                    'LANGUAGE' => trans('constants.LANGUAGE'),
                    'CN' => $barclaycardToken->customer_number ?? '',
                    'EMAIL' => $barclaycardToken->email ?? '',
                    'ACCEPTURL' => route('accept-payment'),
                    'DECLINEURL' => route('decline-payment'),
                    'EXCEPTIONURL' => route('exception-payment'),
                    'CANCELURL' => route('cancel-payment'),
                    'ALIAS' => $barclaycardToken->token,
                    'ALIASUSAGE' => trans('constants.ALIASUSAGE'),
                    'ALIASOPERATION' => trans('constants.ALIASOPERATION'),
                    'ECI' => trans('constants.ECI'),
                    'USERID' => trans('constants.USERID'),
                    'PSWD' => trans('constants.PSWD')
                ];

                $params['SHASIGN'] = $this->generateHashSipAutoTopup($params);

                $result = $this->proxyPaymentAutoTopup($params);

                if (isset($result['error']) && $result['error']) {
                } else {

                    $invoice->update([
                        'pay_status' => 'Active',
                        'paid_date' => now()
                    ]);
                }
            }
        }
    }

    public function sendWelcomeEmail(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'email_message' => 'required|string',
        ]);

        // Find the user by ID
        $user = User::find($request->input('user_id'));

        // Get the message content from the request
        $messageContent = $request->input('email_message');

        try {
            // Send the email
            Mail::send('mail.subscription-welcome', [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'messageContent' => $messageContent
            ], function ($message) use ($user) {
                $message->to($user->email)
                    ->subject('Welcome to Our Service!');
            });

            return redirect()->back()->with('success', 'Welcome email sent successfully!');
        } catch (\Exception $e) {
            Log::error('Failed to send welcome email: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to send welcome email. Please try again later.');
        }
    }
}
