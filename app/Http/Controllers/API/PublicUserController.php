<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PublicUserController extends Controller
{
    /**
     * The user repository instance.
     *
     * @var \App\Repositories\UserRepository
     */
    protected $userRepository;

    /**
     * Create a new controller instance.
     *
     * @param  \App\Repositories\UserRepository  $userRepository
     * @return void
     */
    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * Display a listing of users with filtering, sorting, and pagination.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            // Log access with relevant details
            Log::info('Public users index accessed', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'path' => $request->path(),
                'query_params' => $request->query(),
            ]);
            
            // Get parameters for filtering, sorting, and pagination
            $params = [
                'search' => $request->query('q'), // Frontend uses 'q' for search
                'role' => $request->query('role'),
                'status' => $request->query('status'),
                'sort' => $request->query('sortBy', 'created_at'),
                'order' => $request->query('orderBy', 'desc'),
                'itemsPerPage' => (int)$request->query('itemsPerPage', 15),
                'page' => (int)$request->query('page', 1),
            ];
            
            // Use the repository to get users
            $result = $this->userRepository->getUsers($params);
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Error in public users index', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Failed to fetch users',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'An error occurred while fetching users'
            ], 500);
        }
    }

    /**
     * Display the specified user.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            // Get user from repository
            $user = $this->userRepository->getUserById($id);
            
            return response()->json([
                'user' => $user
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error in public user show', [
                'id' => $id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Failed to fetch user',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'An error occurred while fetching the user'
            ], 500);
        }
    }
} 