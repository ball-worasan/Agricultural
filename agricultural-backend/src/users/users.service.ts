import {
  BadRequestException,
  ConflictException,
  Injectable,
  Logger,
  NotFoundException,
} from '@nestjs/common';
import { InjectModel } from '@nestjs/mongoose';
import { Model } from 'mongoose';
import * as bcrypt from 'bcrypt';
import { ConfigService } from '@nestjs/config';
import { User, UserDocument } from './schemas/user.schema';
import { CreateUserDto } from './dto/create-user.dto';
import { UpdateUserDto } from './dto/update-user.dto';

@Injectable()
export class UsersService {
  private readonly logger = new Logger(UsersService.name);
  private readonly saltRounds: number;

  constructor(
    @InjectModel(User.name) private readonly userModel: Model<UserDocument>,
    private readonly cfg: ConfigService,
  ) {
    // ไทย: ตั้งรอบ bcrypt จาก env (ปลอดภัยกว่า)
    this.saltRounds = this.cfg.get<number>('auth.bcryptSaltRounds', 12);
    this.logger.log(`UsersService initialized (saltRounds=${this.saltRounds})`);
  }

  private sanitize(user: UserDocument | (User & { _id: any })) {
    const obj = typeof (user as any).toJSON === 'function' ? (user as any).toJSON() : user;
    delete (obj as any).passwordHash;
    return obj;
  }

  async create(dto: CreateUserDto) {
    this.logger.debug(`create user requested (email=${dto.email.toLowerCase()}, username=${dto.username.toLowerCase()})`);
    // ไทย: กันซ้ำเบื้องต้น
    const existing = await this.userModel
      .findOne({ $or: [{ email: dto.email.toLowerCase() }, { username: dto.username.toLowerCase() }] })
      .lean();
    if (existing) {
      this.logger.warn(`create user conflict (email/username exists)`);
      throw new ConflictException('email or username already exists');
    }

    const passwordHash = await bcrypt.hash(dto.password, this.saltRounds);
    const doc = new this.userModel({
      fullname: dto.fullname,
      username: dto.username.toLowerCase(),
      email: dto.email.toLowerCase(),
      address: dto.address,
      phone: dto.phone,
      passwordHash,
    });

    try {
      const saved = await doc.save();
      this.logger.log(`user created (id=${saved._id})`);
      return this.sanitize(saved);
    } catch (err: any) {
      if (err?.code === 11000) {
        this.logger.warn(`duplicate key on create user`);
        throw new ConflictException('duplicate key: email or username');
      }
      this.logger.error(`create user failed: ${err?.message}`, err?.stack);
      throw new BadRequestException(err?.message || 'create user failed');
    }
  }

  async findAll() {
    this.logger.debug(`findAll users`);
    const list = await this.userModel.find().lean();
    return list.map((u) => this.sanitize(u as any));
  }

  async findById(id: string) {
    this.logger.debug(`findById (id=${id})`);
    const user = await this.userModel.findById(id);
    if (!user) {
      this.logger.warn(`user not found (id=${id})`);
      throw new NotFoundException('user not found');
    }
    return this.sanitize(user);
  }

  async findByEmailWithPassword(email: string) {
    this.logger.debug(`findByEmailWithPassword (email=${email.toLowerCase()})`);
    return this.userModel.findOne({ email: email.toLowerCase() }).select('+passwordHash').exec();
  }

  async findByUsernameWithPassword(username: string) {
    this.logger.debug(`findByUsernameWithPassword (username=${username.toLowerCase()})`);
    return this.userModel.findOne({ username: username.toLowerCase() }).select('+passwordHash').exec();
  }

  async update(id: string, dto: UpdateUserDto) {
    this.logger.debug(`update user (id=${id})`);
    const update: any = { ...dto };

    // ไทย: เปลี่ยนรหัสผ่าน => hash ใหม่
    if (dto.password) {
      update.passwordHash = await bcrypt.hash(dto.password, this.saltRounds);
      delete update.password;
    }

    if (dto.email) update.email = dto.email.toLowerCase();
    if (dto.username) update.username = dto.username.toLowerCase();

    try {
      const updated = await this.userModel.findByIdAndUpdate(id, update, { new: true });
      if (!updated) {
        this.logger.warn(`user not found on update (id=${id})`);
        throw new NotFoundException('user not found');
      }
      this.logger.log(`user updated (id=${id})`);
      return this.sanitize(updated);
    } catch (err: any) {
      if (err?.code === 11000) {
        this.logger.warn(`duplicate key on update user`);
        throw new ConflictException('duplicate key: email or username');
      }
      this.logger.error(`update user failed: ${err?.message}`, err?.stack);
      throw new BadRequestException(err?.message || 'update user failed');
    }
  }

  async remove(id: string) {
    this.logger.debug(`remove user (id=${id})`);
    const res = await this.userModel.findByIdAndDelete(id);
    if (!res) {
      this.logger.warn(`user not found on remove (id=${id})`);
      throw new NotFoundException('user not found');
    }
    this.logger.log(`user removed (id=${id})`);
    return { ok: true };
  }
}
