import {
  Body,
  Controller,
  Get,
  Logger,
  Post,
  Req,
  UseGuards,
} from '@nestjs/common';
import {
  ApiBearerAuth,
  ApiBody,
  ApiCreatedResponse,
  ApiOkResponse,
  ApiOperation,
  ApiTags,
  ApiUnauthorizedResponse,
  ApiBadRequestResponse,
  ApiConflictResponse,
} from '@nestjs/swagger';
import { AuthService } from './auth.service';
import { LoginDto } from './dto/login.dto';
import { JwtAuthGuard } from './jwt-auth.guard';
import { AuthRegisterDto } from './dto/auth-register.dto';
import { UsersService } from '../users/users.service';

@ApiTags('Auth')
@Controller('auth')
export class AuthController {
  private readonly logger = new Logger(AuthController.name);
  constructor(
    private readonly authService: AuthService,
    private readonly usersService: UsersService,
  ) {}

  @ApiOperation({ summary: 'User login (username or email + password)' })
  @ApiBody({ type: LoginDto })
  @ApiOkResponse({
    description: 'Login success',
    schema: {
      example: {
        ok: true,
        user: { _id: '...', username: 'ball.admin', email: 'ball@example.com' },
        token: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...',
      },
    },
  })
  @ApiUnauthorizedResponse({ description: 'Invalid credentials' })
  @Post('login')
  async login(@Body() dto: LoginDto) {
    const result = await this.authService.login(dto.identifier, dto.password);
    return result;
  }

  @ApiOperation({ summary: 'Register new user' })
  @ApiBody({ type: AuthRegisterDto })
  @ApiCreatedResponse({
    description: 'Register success',
    schema: {
      example: {
        ok: true,
        user: { _id: '...', username: 'ball.admin', email: 'ball@example.com' },
        token: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...',
      },
    },
  })
  @ApiBadRequestResponse({ description: 'Validation error' })
  @ApiConflictResponse({ description: 'Duplicate email or username' })
  @Post('register')
  async register(@Body() dto: AuthRegisterDto) {
    const user = await this.usersService.create({
      fullname: dto.fullName,
      username: dto.username,
      email: dto.email,
      address: dto.address,
      phone: dto.contactPhone,
      password: dto.password,
    } as any);
    const token = await this.authService.signToken({
      sub: (user as any)._id?.toString?.() ?? (user as any)['_id'],
      username: user.username,
      email: user.email,
    });
    return { ok: true, user, token };
  }

  @ApiOperation({ summary: 'Get current user (full profile via JWT)' })
  @ApiBearerAuth('bearer')
  @ApiOkResponse({ description: 'Current user profile' })
  @UseGuards(JwtAuthGuard)
  @Get('me')
  async me(@Req() req: any) {
    const full = await this.usersService.findById(req.user.sub);
    return { ok: true, user: full };
  }
}
