<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use Database\Factories\CompanyMailSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The mailbox a company's emails are sent from (email automation): the SMTP login of its `recruitment@` address and the
 * From and Reply-To it uses. Set by an admin on the company's page; nothing is sent until it is enabled.
 *
 * @property int $id
 * @property int $company_id
 * @property string $host
 * @property int $port
 * @property string $encryption 'ssl' or 'tls'
 * @property string $username
 * @property string $password
 * @property string $from_address
 * @property string|null $from_name
 * @property string|null $reply_to
 * @property bool $is_enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'company_id',
    'host',
    'port',
    'encryption',
    'username',
    'password',
    'from_address',
    'from_name',
    'reply_to',
    'is_enabled',
])]
class CompanyMailSetting extends Model
{
    /** @use HasFactory<CompanyMailSettingFactory> */
    use BelongsToCompany, HasFactory;

    public const ENCRYPTIONS = [
        'ssl' => 'SSL/TLS (biasanya port 465)',
        'tls' => 'STARTTLS (biasanya port 587)',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'password' => 'encrypted',
            'is_enabled' => 'boolean',
        ];
    }

    /**
     * The mailer configuration Laravel sends through. Implicit TLS is the `smtps` scheme; STARTTLS is plain `smtp`, which
     * upgrades the connection when the server offers it.
     *
     * @return array<string, mixed>
     */
    public function mailerConfig(): array
    {
        return [
            'transport' => 'smtp',
            'scheme' => $this->encryption === 'ssl' ? 'smtps' : 'smtp',
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'password' => $this->password,
            'timeout' => 20,
        ];
    }
}
