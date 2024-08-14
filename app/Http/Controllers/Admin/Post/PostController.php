<?php

namespace App\Http\Controllers\Admin\Post;

use App\Constants\General\NotificationConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Http\Controllers\Controller;
use App\Services\Post\PostService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PostController extends Controller
{
    protected $post_service;

    public function __construct()
    {
        $this->post_service = new PostService;
    }

    public function store(Request $request)
    {
        try {
            $this->post_service->create($request->all());
            return redirect()->route("admin.post-categories.index")->with(NotificationConstants::SUCCESS_MSG, "Post created successfully");
        } catch (ValidationException $th) {
            throw $th;
        } catch (\Throwable $th) {
            // throw $th;
            return redirect()->back()->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request.");
        }
    }

    public function update(Request $request, $post_id)
    {
        try {
            $this->post_service->update($request->all(), $post_id);
            return redirect()->back()->with(NotificationConstants::SUCCESS_MSG, "Post updated successfully");
        } catch (ValidationException $th) {
            throw $th;
        } catch (ModelNotFoundException $th) {
            return redirect()->back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (InvalidRequestException $th) {
            return redirect()->back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (\Throwable $th) {
            // throw $th;
            return redirect()->back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request.");
        }
    }

    public function destroy(Request $request, $post_id)
    {
        try {
            $this->post_service->delete($post_id);
            return redirect()->back()->with(NotificationConstants::SUCCESS_MSG, "Post deleted successfully");
        } catch (ModelNotFoundException $th) {
            return redirect()->back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (InvalidRequestException $th) {
            return redirect()->back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (\Throwable $th) {
            // throw $th;
            return redirect()->back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request.");
        }
    }
}
