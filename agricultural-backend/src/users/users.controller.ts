import {
  Body,
  Controller,
  Delete,
  Get,
  Logger,
  Param,
  Patch,
  Post,
  UseGuards,
} from '@nestjs/common';
import {
  ApiBearerAuth,
  ApiBody,
  ApiCreatedResponse,
  ApiOkResponse,
  ApiOperation,
  ApiTags,
  ApiBadRequestResponse,
  ApiNotFoundResponse,
  ApiConflictResponse,
} from '@nestjs/swagger';
import { UsersService } from './users.service';
import { CreateUserDto } from './dto/create-user.dto';
import { UpdateUserDto } from './dto/update-user.dto';
import { AuthService } from '../auth/auth.service';
import { JwtAuthGuard } from '../auth/jwt-auth.guard';

function maskEmail(email: string) {
  /* ...ตามเดิม... */
}

@ApiTags('Users')
@ApiBearerAuth('bearer')
@UseGuards(JwtAuthGuard)
@Controller('users')
export class UsersController {
  private readonly logger = new Logger(UsersController.name);
  constructor(
    private readonly usersService: UsersService,
    private readonly authService: AuthService,
  ) {}

  // NOTE: signup แบบ public ถูกย้ายไปที่ /auth/register แล้ว
  // เส้นทางด้านล่างนี้เป็น admin/internal ที่ต้องมี JWT

  @ApiOperation({ summary: 'Create user (admin/internal)' })
  @ApiBody({ type: CreateUserDto })
  @ApiCreatedResponse({
    description: 'Created',
    schema: {
      example: {
        /* ... */
      },
    },
  })
  @ApiConflictResponse({ description: 'Duplicate email/username' })
  @Post()
  create(@Body() dto: CreateUserDto) {
    this.logger.debug(`admin create user`);
    return this.usersService.create(dto);
  }

  @ApiOperation({ summary: 'List users' })
  @ApiOkResponse({
    description: 'List',
    schema: { example: [{ _id: '...', username: 'ball.admin', email: '...' }] },
  })
  @Get()
  findAll() {
    this.logger.debug(`admin findAll`);
    return this.usersService.findAll();
  }

  @ApiOperation({ summary: 'Get user by id' })
  @ApiOkResponse({ description: 'User found' })
  @ApiNotFoundResponse({ description: 'User not found' })
  @Get(':id')
  findOne(@Param('id') id: string) {
    this.logger.debug(`admin findOne (id=${id})`);
    return this.usersService.findById(id);
  }

  @ApiOperation({ summary: 'Update user by id' })
  @ApiBody({ type: UpdateUserDto })
  @ApiOkResponse({ description: 'Updated' })
  @ApiNotFoundResponse({ description: 'User not found' })
  @ApiConflictResponse({ description: 'Duplicate email/username' })
  @Patch(':id')
  update(@Param('id') id: string, @Body() dto: UpdateUserDto) {
    this.logger.debug(`admin update (id=${id})`);
    return this.usersService.update(id, dto);
  }

  @ApiOperation({ summary: 'Remove user by id' })
  @ApiOkResponse({ description: 'Removed', schema: { example: { ok: true } } })
  @ApiNotFoundResponse({ description: 'User not found' })
  @Delete(':id')
  remove(@Param('id') id: string) {
    this.logger.debug(`admin remove (id=${id})`);
    return this.usersService.remove(id);
  }
}
