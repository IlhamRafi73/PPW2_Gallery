<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Http\Controllers\Controller;
use App\Mail\SendEmail;
use Illuminate\Http\Request;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage; 
use Illuminate\Support\Facades\File;
use Intervention\Image\Facades\Image;


class LoginRegisterAuthController extends Controller
{
    public function _construct()
    {
        $this->middleware('guest')->except(['logout', 'dashboard']);
    }

    public function register()
    {
        return view('auth.register');
    }

    public function Store (Request $request){
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:4|confirmed',
            'photo' => 'image|nullable|max:1999'
        ]);

        if($request->hasFile('photo')){
            $filenameWithExt = $request->file('photo')->getClientOriginalName();
            $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
            $extension = $request->file('photo')->getClientOriginalExtension();
            $fileNameToStore = $filename.'_'.time().'.'.$extension;
            $path = $request->file('photo')->storeAs('storage/photos/', $fileNameToStore);
        }else{
           //
        }

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password'=> hash::make($request->password),
            'photo' => $path
        ]);

        $content = [
            'subject' => $request->name,
            'body' => $request->email 
        ];
        
        Mail::to($request->email)->send(new SendEmail($content));

        $credentials = $request->only('email','password');
        Auth::attempt($credentials);
        $request->session()->regenerate();
        return redirect()->route('dashboard')
        ->withSuccess("Welcome! You have Successfully logged in");
    }

   public function login(){
         return view('auth.login');
   } 

   public function authenticate(Request $request){
         $credentials = $request->validate([
                'email' => 'required', 'email',
                'password' => 'required',
         ]);

         if(Auth::attempt($credentials)){
              $request->session()->regenerate();
              return redirect()->route('dashboard')
              ->withSuccess("Welcome! You have Successfully loggedin");
         }
         return back()->withErrors([
              'email' => 'The provided credentials do not match our records',
         ])->onlyInput('email');
   }

    public function dashboard(){
        
        
        if (Auth::check()) {
            $user = Auth::user(); 
            return view('auth.dashboard');
        }
        return redirect()->route('login')
        ->withErrors(['You are not allowed to access',])->onlyInput('email');

    }

    public function logout(Request $request){
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login')
        ->withSuccess("You have logged out");
    }

    public function users()
    {
        $users = User::all(); // Fetch all registered users

        return view('users', compact('users'));
    }

    public function editProfile($id)
    {
        $user = User::find($id);
        return view('editprofile', compact('user'));
    }

    public function updateProfile(Request $request, $id)
    {
        $user = User::find($id);

        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email,' . $id,
            'password' => 'nullable|string|min:6',
            'photo' => 'image|nullable|max:1999'
        ]);

        $user->name = $request->input('name');
        $user->email = $request->input('email');

        if ($request->has('password')) {
            $user->password = Hash::make($request->input('password'));
        }

        if ($request->hasFile('photo')) {
            $fileName = time() . '.' . $request->file('photo')->getClientOriginalExtension();
            $request->file('photo')->storeAs('storage/photos/', $fileName);

            // Resize dan simpan gambar asli
            $image = Image::make($request->file('photo')->getRealPath());
            $image->stream();
            $image->save(public_path('storage/photos/' . $fileName));

            // Resize dan simpan thumbnail
            $thumbnail = Image::make($request->file('photo')->getRealPath());
            $thumbnail->resize(150, 100); // Ubah ukuran sesuai kebutuhan
            $thumbnailFileName = time() . '_thumbnail.' . $request->file('photo')->getClientOriginalExtension();
            $thumbnail->save(public_path('storage/photos/' . $thumbnailFileName));

            // Resize dan simpan gambar persegi
            $square = Image::make($request->file('photo')->getRealPath());
            $square->fit(150, 150); // Ubah ukuran sesuai kebutuhan
            $squareFileName = time() . '_square.' . $request->file('photo')->getClientOriginalExtension();
            $square->save(public_path('storage/photos/' . $squareFileName));

            $user->photo = $fileName;
            $user->thumbnail = $thumbnail->basename;
            $user->square = $square->basename;
        }

        // Simpan perubahan atribut lain yang ingin diedit
        $user->save();

        return redirect()->route('users-list')->withSuccess("Profil berhasil diperbarui");
    }

    public function deletePhotos($id)
    {
        $user = User::find($id);

        if ($user->photo) {
            // Hapus gambar asli dari penyimpanan
            Storage::delete('storage/photos/' . $user->photo);
            $user->photo = null;
        }

        if ($user->thumbnail) {
            // Hapus gambar thumbnail dari penyimpanan
            Storage::delete('storage/photos/' . $user->thumbnail);
            $user->thumbnail = null;
        }

        if ($user->square) {
            // Hapus gambar persegi dari penyimpanan
            Storage::delete('storage/photos/' . $user->square);
            $user->square = null;
        }

        $user->save();

        return redirect()->back()->with('success', 'Gambar berhasil dihapus.');
    }
}
