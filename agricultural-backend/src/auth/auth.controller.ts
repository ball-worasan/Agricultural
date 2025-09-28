import { Body, Controller, Get, Logger, Post, Req, UseGuards } from '@nestjs/common';
import { AuthService } from './auth.service';
import { LoginDto } from './dto/login.dto';
import { JwtAuthGuard } from './jwt-auth.guard';

function maskIdentifier(id: string) {
  // ไทย: ปกปิด identifier ในล็อก
  if (id.includes('@')) {
    const [u, d] = id.split('@');
    return `${u?.[0] ?? '*'}***@${d ?? '***'}`;
  }
  return `${id?.[0] ?? '*'}***`;
}

@Controller('auth')
export class AuthController {
  private readonly logger = new Logger(AuthController.name);

  constructor(private readonly authService: AuthService) {}

  @Post('login')
  async login(@Body() dto: LoginDto) {
    this.logger.log(`login attempt (id=${maskIdentifier(dto.identifier)})`);
    const result = await this.authService.login(dto.identifier, dto.password);
    this.logger.log(`login success (id=${maskIdentifier(dto.identifier)})`);
    return result;
  }

  @UseGuards(JwtAuthGuard)
  @Get('me')
  me(@Req() req: any) {
    this.logger.debug(`me requested (sub=${req.user?.sub})`);
    return { ok: true, user: req.user };
  }
}
