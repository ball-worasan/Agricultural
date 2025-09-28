import {
  Body,
  Controller,
  Delete,
  Get,
  Logger,
  Param,
  Patch,
  Post,
} from '@nestjs/common';
import { UsersService } from './users.service';
import { CreateUserDto } from './dto/create-user.dto';
import { UpdateUserDto } from './dto/update-user.dto';
import { AuthService } from '../auth/auth.service';

function maskEmail(email: string) {
  // ไทย: ปกปิดอีเมลในล็อก
  const [u, d] = email.split('@');
  return `${u?.[0] ?? '*'}***@${d ?? '***'}`;
}

@Controller('users')
export class UsersController {
  private readonly logger = new Logger(UsersController.name);

  constructor(
    private readonly usersService: UsersService,
    private readonly authService: AuthService,
  ) {}

  // ไทย: สมัครสมาชิก (public)
  @Post('signup')
  async signup(@Body() dto: CreateUserDto) {
    this.logger.log(`signup requested (email=${maskEmail(dto.email)}, username=${dto.username})`);
    const user = await this.usersService.create(dto);
    const token = await this.authService.signToken({
      sub: (user as any)._id?.toString?.() ?? (user as any)['_id'],
      username: user.username,
      email: user.email,
    });
    this.logger.log(`signup success (id=${(user as any)._id})`);
    return { ok: true, user, token };
  }

  // ไทย: CRUD (admin/internal)
  @Post()
  create(@Body() dto: CreateUserDto) {
    this.logger.debug(`admin create user`);
    return this.usersService.create(dto);
  }

  @Get()
  findAll() {
    this.logger.debug(`admin findAll`);
    return this.usersService.findAll();
  }

  @Get(':id')
  findOne(@Param('id') id: string) {
    this.logger.debug(`admin findOne (id=${id})`);
    return this.usersService.findById(id);
  }

  @Patch(':id')
  update(@Param('id') id: string, @Body() dto: UpdateUserDto) {
    this.logger.debug(`admin update (id=${id})`);
    return this.usersService.update(id, dto);
  }

  @Delete(':id')
  remove(@Param('id') id: string) {
    this.logger.debug(`admin remove (id=${id})`);
    return this.usersService.remove(id);
  }
}
