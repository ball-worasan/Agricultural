import { Injectable, Logger, UnauthorizedException } from '@nestjs/common';
import { JwtService } from '@nestjs/jwt';
import * as bcrypt from 'bcrypt';
import { UsersService } from '../users/users.service';

@Injectable()
export class AuthService {
  private readonly logger = new Logger(AuthService.name);

  constructor(
    private readonly usersService: UsersService,
    private readonly jwtService: JwtService,
  ) {}

  async validateUser(identifier: string, password: string) {
    // ไทย: แยกตาม email / username
    const byEmail = identifier.includes('@');
    const user = byEmail
      ? await this.usersService.findByEmailWithPassword(identifier)
      : await this.usersService.findByUsernameWithPassword(identifier);

    if (!user) {
      this.logger.warn(`validate failed (user not found)`);
      throw new UnauthorizedException('invalid credentials');
    }

    const ok = await bcrypt.compare(password, user.passwordHash);
    if (!ok) {
      this.logger.warn(`validate failed (wrong password)`);
      throw new UnauthorizedException('invalid credentials');
    }

    this.logger.debug(`validate ok (id=${user._id})`);
    return (user.toJSON?.() ?? user) as any;
  }

  async signToken(payload: { sub: string; username: string; email: string }) {
    // ไทย: เซ็น JWT (อย่าล็อก token จริง)
    return this.jwtService.signAsync(payload);
  }

  async login(identifier: string, password: string) {
    const user = await this.validateUser(identifier, password);
    const token = await this.signToken({
      sub: (user as any)._id?.toString?.() ?? user['_id'],
      username: user.username,
      email: user.email,
    });
    this.logger.log(`login issued token (sub=${(user as any)._id})`);
    return { ok: true, user, token };
  }
}
