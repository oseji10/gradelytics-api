<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
// Add this import
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'firstName',
        'lastName',
        'otherNames',
        'email',
        'password',
        'role',
        'phoneNumber',
        'otp_code',
        'otp_expires_at',
        'email_verified_at',
        'profileSlug',
        'avatar',
        'profileImage',
        'status',
        'currentPlan', 
        'alternatePhoneNumber'

    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'otp_code',
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'otp_expires_at' => 'datetime',
            'last_seen' => 'datetime', // Add this
            'password' => 'hashed',
        ];
    }

    /**
     * JWT Identifier
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * JWT Custom Claims
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    /**
     * User role relationship
     */
    public function user_role()
    {
        return $this->belongsTo(Role::class, 'role', 'roleId');
    }


     public function user_plan()
    {
        return $this->belongsTo(Plans::class, 'planId', 'currentPlan');
    }




    public function current_plan()
    {
        return $this->hasOne(Plans::class, 'planId', 'currentPlan');
    }

    public function plan()
{
    return $this->belongsTo(Plans::class, 'currentPlan', 'planId');
}


    // Profile relationship
//  public function default_school()
// {
//     return $this->hasMany(School::class, 'ownerId', 'id')
//                 ->where('isDefault', 1);
// }


public function default_school()
{
    return $this->belongsToMany(
        School::class,
        'school_staff',
        'staffId',
        'schoolId'
    );
}

public function currently_active_school()
{
    return $this->belongsToMany(
        School::class,
        'school_staff',
        'staffId',
        'schoolId'
    )->where('isDefault', 1)->first();
}

public function activeSchool()
{
    return $this->currently_active_school()->first();
}



    public function schools()
{
    return $this->hasMany(school::class, 'ownerId', 'id');
}


// public function canCreateschool(): bool
//     {
//         if ($this->currentPlan === "2") {
//             return true;
//         }
//         return $this->default_school()->count() < 2;
//     }

public function canCreateschool(): bool
{
    $plan = $this->plan;

    // No plan assigned → deny by default
    if (!$plan) {
        return false;
    }

    // Unlimited schools
    if ($plan->schoolLimit === null) {
        return true;
    }

    return $this->schools()->count() < $plan->schoolLimit;
}

    // public function canCreateInvoice(): bool
    // {
    //     if ($this->currentPlan === "2") {
    //         return true;
    //     }
    //     return $this->invoices()->count() < 3;
    // }


    public function canCreateInvoice(): bool
{
    $plan = $this->plan;

    // No plan assigned → deny by default
    if (!$plan) {
        return false;
    }

    // Unlimited schools
    if ($plan->invoiceLimit === null) {
        return true;
    }

    return $this->invoices()->count() < $plan->invoiceLimit;
}

public function subscription()
{
    return $this->hasOne(Subscription::class, 'userId', 'id');
}


public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'userId');
    }

    public function activeSubscription()
    {
        return $this->subscriptions()
            ->where('status', 'active')
            ->latest();
    }

    public function teacher()
{
    return $this->hasOne(Teacher::class, 'userId', 'id');
}

}
