<?php

use App\Enums\EmploymentStatus;
use App\Enums\Role;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The profile picture on the employee record, as shown on the directory cards.
 */
beforeEach(function () {
    Storage::fake('public');
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create();
    $this->hr = User::factory()->create(['company_id' => $this->company->id]);
    $this->hr->assignRole(Role::HrAdmin->value);
});

/**
 * A stand-in image. UploadedFile::fake()->image() needs the GD extension, which the container
 * does not ship; the upload rules only look at the reported type and size.
 */
function fakePhoto(string $name, int $kilobytes = 20): UploadedFile
{
    $mime = match (true) {
        str_ends_with($name, '.png') => 'image/png',
        str_ends_with($name, '.webp') => 'image/webp',
        default => 'image/jpeg',
    };

    return UploadedFile::fake()->create($name, $kilobytes, $mime);
}

function newEmployeePayload(array $overrides = []): array
{
    return [
        'employee_number' => 'EMP-7001',
        'full_name' => 'Dewi Lestari',
        'employment_status' => EmploymentStatus::Active->value,
        ...$overrides,
    ];
}

it('stores an uploaded photo and exposes its URL to the directory', function () {
    $this->actingAs($this->hr)
        ->post(route('employees.store'), newEmployeePayload(['photo' => fakePhoto('dewi.jpg')]))
        ->assertRedirect();

    $employee = Employee::firstWhere('employee_number', 'EMP-7001');

    expect($employee->photo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($employee->photo_path);

    $this->actingAs($this->hr)
        ->get(route('employees.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('employees.0.photoUrl', Storage::disk('public')->url($employee->photo_path)));
});

it('keeps the WebP file the browser sends after shrinking the picture', function () {
    $this->actingAs($this->hr)
        ->post(route('employees.store'), newEmployeePayload(['photo' => fakePhoto('dewi.webp', 60)]))
        ->assertRedirect();

    $path = Employee::firstWhere('employee_number', 'EMP-7001')->photo_path;

    expect($path)->toEndWith('.webp');
    Storage::disk('public')->assertExists($path);
});

it('gives a null photoUrl when there is no picture', function () {
    Employee::factory()->forCompany($this->company)->create();

    $this->actingAs($this->hr)
        ->get(route('employees.index'))
        ->assertInertia(fn (Assert $page) => $page->where('employees.0.photoUrl', null));
});

it('replaces a photo and deletes the old file', function () {
    $employee = Employee::factory()->forCompany($this->company)->create([
        'photo_path' => fakePhoto('old.jpg')->store('employee-photos', 'public'),
    ]);
    $old = $employee->photo_path;

    $this->actingAs($this->hr)
        ->put(route('employees.update', $employee), [
            ...newEmployeePayload(['employee_number' => $employee->employee_number]),
            'photo' => fakePhoto('new.png'),
        ])
        ->assertRedirect();

    $employee->refresh();

    expect($employee->photo_path)->not->toBe($old);
    Storage::disk('public')->assertMissing($old);
    Storage::disk('public')->assertExists($employee->photo_path);
});

it('removes a photo on request', function () {
    $employee = Employee::factory()->forCompany($this->company)->create([
        'photo_path' => fakePhoto('old.jpg')->store('employee-photos', 'public'),
    ]);
    $old = $employee->photo_path;

    $this->actingAs($this->hr)
        ->put(route('employees.update', $employee), [
            ...newEmployeePayload(['employee_number' => $employee->employee_number]),
            'remove_photo' => '1',
        ])
        ->assertRedirect();

    expect($employee->fresh()->photo_path)->toBeNull();
    Storage::disk('public')->assertMissing($old);
});

it('keeps the existing photo when an edit does not touch it', function () {
    $employee = Employee::factory()->forCompany($this->company)->create([
        'photo_path' => fakePhoto('old.jpg')->store('employee-photos', 'public'),
    ]);

    $this->actingAs($this->hr)
        ->put(route('employees.update', $employee), newEmployeePayload([
            'employee_number' => $employee->employee_number,
            'full_name' => 'Renamed',
        ]))
        ->assertRedirect();

    expect($employee->fresh()->photo_path)->toBe($employee->photo_path);
});

it('rejects a file that is not an image, or is too large', function () {
    $this->actingAs($this->hr)
        ->post(route('employees.store'), newEmployeePayload(['photo' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf')]))
        ->assertSessionHasErrors('photo');

    $this->actingAs($this->hr)
        ->post(route('employees.store'), newEmployeePayload(['photo' => fakePhoto('big.jpg', 3000)]))
        ->assertSessionHasErrors('photo');

    expect(Employee::where('employee_number', 'EMP-7001')->exists())->toBeFalse();
});
