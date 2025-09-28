import { Prop, Schema, SchemaFactory } from '@nestjs/mongoose';
import { HydratedDocument } from 'mongoose';

export type UserDocument = HydratedDocument<User>;

@Schema({
  collection: 'users',
  timestamps: { createdAt: 'createdAt', updatedAt: 'updatedAt' },
  versionKey: false,
})
export class User {
  _id!: any;

  @Prop({ required: true, trim: true })
  fullname!: string; // ไทย: ชื่อเต็ม

  @Prop({ required: true, trim: true, lowercase: true, unique: true, index: true })
  username!: string; // ไทย: เล็กหมดเพื่อกันซ้ำ

  @Prop({ required: true, trim: true, lowercase: true, unique: true, index: true })
  email!: string; // ไทย: เล็กหมดเพื่อกันซ้ำ

  @Prop({ trim: true })
  address?: string;

  @Prop({ trim: true, index: true })
  phone?: string;

  @Prop({ required: true, select: false })
  passwordHash!: string; // ไทย: เก็บเฉพาะแฮช ไม่ดึงออกโดยดีฟอลต์

  createdAt!: Date;
  updatedAt!: Date;
}

export const UserSchema = SchemaFactory.createForClass(User);

// ไทย: hide passwordHash ตอน toJSON
UserSchema.set('toJSON', {
  transform: function (_doc, ret: any) {
    delete ret.passwordHash;
    return ret;
  },
});
