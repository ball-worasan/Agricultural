import {
  Body,
  Controller,
  Patch,
  Post,
  Req,
  UseGuards,
  Logger,
  BadRequestException,
} from '@nestjs/common';
import {
  ApiBearerAuth,
  ApiOkResponse,
  ApiOperation,
  ApiTags,
  ApiBadRequestResponse,
} from '@nestjs/swagger';
import { JwtAuthGuard } from '../auth/jwt-auth.guard';
import { UsersService } from './users.service';
import { UpdateProfileDto } from './dto/update-profile.dto';
import { ChangePasswordDto } from './dto/change-password.dto';
import { ConfigService } from '@nestjs/config';
import * as bcrypt from 'bcrypt';

@ApiTags('Account')
@ApiBearerAuth('bearer')
@UseGuards(JwtAuthGuard)
@Controller('account')
export class AccountController {
  private readonly logger = new Logger(AccountController.name);
  private readonly saltRounds: number;
  private readonly cooldownMinutes: number;

  constructor(
    private readonly usersService: UsersService,
    private readonly cfg: ConfigService,
  ) {
    this.saltRounds = this.cfg.get<number>('auth.bcryptSaltRounds', 12);
    this.cooldownMinutes = parseInt(
      process.env.PASSWORD_CHANGE_COOLDOWN_MINUTES ?? '5',
      10,
    );
  }

  @ApiOperation({ summary: 'Update own profile' })
  @ApiOkResponse({ description: 'Updated' })
  @ApiBadRequestResponse({ description: 'Validation error' })
  @Patch('profile')
  async updateProfile(@Req() req: any, @Body() dto: UpdateProfileDto) {
    const uid = req.user.sub as string;
    // map ชื่อฟิลด์จาก DTO → schema
    const payload: any = {};
    if (dto.fullName != null) payload.fullname = dto.fullName;
    if (dto.username != null) payload.username = dto.username;
    if (dto.address != null) payload.address = dto.address;
    if (dto.phone != null) payload.phone = dto.phone;

    const updated = await this.usersService.update(uid, payload);
    return { ok: true, user: updated };
  }

  @ApiOperation({
    summary: `Change password (cooldown ${process.env.PASSWORD_CHANGE_COOLDOWN_MINUTES ?? '5'} min)`,
  })
  @ApiOkResponse({ description: 'Password changed' })
  @ApiBadRequestResponse({ description: 'Validation error' })
  @Post('change-password')
  async changePassword(@Req() req: any, @Body() dto: ChangePasswordDto) {
    const uid = req.user.sub as string;
    if (!dto.password || dto.password !== dto.confirm) {
      throw new BadRequestException('password confirmation mismatch');
    }

    // โหลดโปรไฟล์เพื่อเช็คคูลดาวน์
    const user = await this.usersService.findById(uid);
    const last = user.passwordChangedAt
      ? new Date(user.passwordChangedAt).getTime()
      : 0;
    const now = Date.now();
    const cooldownMs = this.cooldownMinutes * 60 * 1000;

    if (last && now - last < cooldownMs) {
      const remainSec = Math.ceil((cooldownMs - (now - last)) / 1000);
      throw new BadRequestException(
        `Password was changed recently. Please wait ${remainSec}s`,
      );
    }

    // เปลี่ยนรหัสผ่าน
    const passwordHash = await bcrypt.hash(dto.password, this.saltRounds);
    const updated = await this.usersService.update(uid, {
      passwordHash,
      // บันทึกเวลาเปลี่ยน
      passwordChangedAt: new Date(),
    } as any);

    return { ok: true, user: updated };
  }
}
