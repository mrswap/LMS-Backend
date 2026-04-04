<?php

namespace App\Modules\Admin\UserManagement\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    protected $uploadPath = 'uploads/users/profile-images/';
    
    
    public function index(Request $request)
    {
        $query = User::where('role', User::ROLE_SALES)
            ->with('creator:id,name');

        /*
        |-----------------------------
        | SEARCH (optional)
        |-----------------------------
        */
        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        /*
        |-----------------------------
        | STATUS (optional)
        |-----------------------------
        */
        if ($request->has('status')) {
            if ($request->status !== 'all') {
                $query->where('status', (bool) $request->status);
            }
        }

        /*
        |-----------------------------
        | SORTING
        |-----------------------------
        */
        $sortByMap = [
            'createdAt' => 'created_at',
            'name'      => 'name',
            'email'     => 'email',
        ];

        $sortBy = $request->get('sortBy', 'createdAt');
        $order  = strtolower($request->get('order', 'desc')) === 'asc' ? 'asc' : 'desc';

        $sortColumn = $sortByMap[$sortBy] ?? 'created_at';

        $query->orderBy($sortColumn, $order);

        /*
        |-----------------------------
        | PAGINATION
        |-----------------------------
        */
        $limit = (int) $request->get('limit', 10);
        $limit = ($limit > 0 && $limit <= 100) ? $limit : 10;

        $users = $query->paginate($limit);

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users,email',
            'mobile' => 'required',
            'role' => 'required',
            'department' => 'required',
            'region' => 'required',
            'password' => 'required|min:6'
        ]);

        $imagePath = null;

        if ($request->hasFile('profile_image')) {
            $file = $request->file('profile_image');
            $name = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
            $file->move(public_path($this->uploadPath), $name);
            $imagePath = $this->uploadPath . $name;
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'mobile' => $request->mobile,
            'employee_id' => $request->employee_id,
            'role' => $request->role,
            'department' => $request->department,
            'designation' => $request->designation,
            'region' => $request->region,
            'city' => $request->city,
            'password' => Hash::make($request->password),
            'profile_image' => $imagePath,
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'User created successfully',
            'data' => $user
        ]);
    }

    public function show($id)
    {
        return response()->json(User::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        if ($request->hasFile('profile_image')) {
            $file = $request->file('profile_image');
            $name = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
            $file->move(public_path($this->uploadPath), $name);
            $user->profile_image = $this->uploadPath . $name;
        }

        $user->update([
            'name' => $request->name ?? $user->name,
            'mobile' => $request->mobile ?? $user->mobile,
            'employee_id' => $request->employee_id ?? $user->employee_id,
            'department' => $request->department ?? $user->department,
            'designation' => $request->designation ?? $user->designation,
            'region' => $request->region ?? $user->region,
            'city' => $request->city ?? $user->city,
        ]);

        return response()->json([
            'message' => 'User updated successfully',
            'data' => $user
        ]);
    }

    public function destroy($id)
    {
        User::findOrFail($id)->delete();

        return response()->json([
            'message' => 'User deleted successfully'
        ]);
    }

    public function toggleStatus($id)
    {
        $user = User::findOrFail($id);

        $user->is_active = !$user->is_active;
        $user->save();

        return response()->json([
            'status' => $user->is_active
        ]);
    }
}
