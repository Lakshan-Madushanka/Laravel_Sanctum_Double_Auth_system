<?php

namespace App\Exceptions;

use App\Traits\ApiResponser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class Handler extends ExceptionHandler
{
    use ApiResponser;

    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport
        = [
            //
        ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash
        = [
            'password',
            'password_confirmation',
        ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */


    public function register()
    {
        $this->renderable(function (AuthenticationException $e, $request) {
            return $this->unauthenticated($request, $e);
        });

        $this->renderable(function (ValidationException $e, $request) {
            return $this->convertValidationExceptionToResponse($e, $request);
        });

        $this->renderable(function (ModelNotFoundException $e, $request) {
            $model = strtolower(class_basename($e->getModel()));
            return $this->showError('error',
                "Does not exist {$model} for this  identifier");
        });

        $this->renderable(function (AuthorizationException $e, $request) {
            return $this->showError('error', $e->getMessage(), 403);
        });

        $this->renderable(function (ModelNotFoundException $e, $request) {
            $model = strtolower(class_basename($e->getModel()));
            return $this->showError('error',
                "Does not exist {$model} for this  identifier", 404);
        });
        $this->renderable(function (NotFoundHttpException $e, $request) {
            return $this->showError('error', 'URL does not exist',
                $e->getStatusCode());

        });
        $this->renderable(function (ModelNotFoundException $e, $request) {
            $model = strtolower(class_basename($e->getModel()));
            return $this->showError('error',
                "Does not exist {$model} for this  identifier", 404);
        });

        $this->renderable(function (
            MethodNotAllowedHttpException $e,
            $request
        ) {
            return $this->showError('error',
                'Specified method doesn\'t support for this url',
                405);
        });

        $this->renderable(function (HttpException $e, $request) {
            return $this->showError('error', $e->getMessage(),
                $e->getStatusCode());

        });

        $this->renderable(function (QueryException $e, $request) {
            $errorCode = $e->errorInfo[1];
            if ($errorCode == 1451) {
                return $this->errorResponse('Cannot delete this record as it has asscociated with other records',
                    409);
            }
        });

        $this->renderable(function (TokenMismatchException $e, $request) {
            return redirect()->back()->withInput($request->input());
        });

        /*
                if (config('app.debug')) {
                    return parent::render();
                }*/


    }

    protected function unauthenticated(
        $request,
        AuthenticationException $exception
    ) {

        return $this->showError('error', $exception->getMessage(), 401);
    }

    protected function convertValidationExceptionToResponse(
        ValidationException $e,
        $request
    ) {
        $errors = $e->validator->errors()->getMessages();
        if($this->isFrontEnd($request)) {
            return $request->ajax() ? response()->json($errors, 422) : redirect()
                ->back()
                ->withInput($request->input())
                ->withErrors($errors);
        }
        return $this->showErrorWithData('error', 'Invalid inputs', $errors,
            422);
    }

    private function isFrontEnd($request) {

        return $request->acceptsHtml() && collect($request->route()->middleware())->contains('web');
    }

}
